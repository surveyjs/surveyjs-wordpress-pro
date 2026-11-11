<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Made for the plain structure jsPDF writes; anything else throws instead of being rewritten into a damaged file.
class SurveyJS_Pro_Pdf_Encryptor {

    const WHITESPACE = "\0\t\n\f\r ";
    const DELIMITERS = '()<>[]{}/%';
    const RESERVED = array( 'obj', 'endobj', 'stream', 'endstream', 'R', 'xref', 'trailer', 'startxref' );
    const ESCAPES = array( 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C", '(' => '(', ')' => ')', '\\' => '\\' );
    const MAX_DEPTH = 64;
    const MAX_OBJECTS = 1000000;

    private $pdf;
    private $pos = 0;
    private $offsets = array();
    private $free = array();
    private $lengths = array();
    private $encrypt;

    public static function encrypt( $pdf, $password ) {
        return ( new self( (string) $pdf ) )->run( (string) $password );
    }

    private function __construct( $pdf ) {
        $this->pdf = $pdf;
    }

    private static function error( $message ) {
        return new UnexpectedValueException( $message );
    }

    private function run( $password ) {
        if ( 0 !== strncmp( $this->pdf, '%PDF-', 5 ) ) {
            throw self::error( 'Not a PDF file.' );
        }
        $trailer = $this->read_xref();
        foreach ( array( 'Encrypt', 'Prev', 'XRefStm' ) as $key ) {
            if ( null !== self::get( $trailer, $key ) ) {
                throw self::error( sprintf( 'The trailer has /%s.', esc_html( $key ) ) );
            }
        }
        $root = self::get( $trailer, 'Root' );
        $info = self::get( $trailer, 'Info' );
        if ( ! $root || 'ref' !== $root['t'] || ! isset( $this->offsets[ $root['n'] ] ) || ( $info && 'ref' !== $info['t'] ) ) {
            throw self::error( 'The trailer has no valid /Root or /Info.' );
        }
        $id = $this->file_id( self::get( $trailer, 'ID' ) );
        $this->encrypt = new SurveyJS_Pro_Pdf_Aes256( $password, bin2hex( random_bytes( 32 ) ) );

        ksort( $this->offsets );
        $size = max( (int) self::number( self::get( $trailer, 'Size' ) ), array_key_last( $this->offsets ) + 1 );
        if ( $size >= self::MAX_OBJECTS ) {
            throw self::error( 'Too many objects.' );
        }
        $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $positions = array();
        foreach ( $this->offsets as $num => list( $offset, $gen ) ) {
            list( $value, $stream ) = $this->read_object( $num, $gen, $offset );
            if ( $num === $root['n'] ) {
                if ( 'dict' !== $value['t'] || null !== $stream || $gen !== $root['g'] ) {
                    throw self::error( 'The catalog is not a dictionary.' );
                }
                $value = self::with_extensions( $value );
            }
            $positions[ $num ] = strlen( $out );
            $out .= "$num $gen obj\n";
            if ( null === $stream ) {
                $out .= $this->serialize( $value ) . "\nendobj\n";
                continue;
            }
            $data = $this->encrypt->encrypt( $stream );
            $value = self::with( $value, 'Length', array( 't' => 'raw', 'v' => (string) strlen( $data ) ) );
            $out .= $this->serialize( $value ) . "\nstream\n" . $data . "\nendstream\nendobj\n";
        }

        $encryption = $size++;
        $positions[ $encryption ] = strlen( $out );
        $out .= "$encryption 0 obj\n" . $this->encrypt->dictionary() . "\nendobj\n";

        $xref = strlen( $out );
        $out .= $this->xref_table( $positions, $size );
        $out .= "trailer\n<< /Size $size /Root {$root['n']} {$root['g']} R" . ( $info ? " /Info {$info['n']} {$info['g']} R" : '' );
        $out .= " /Encrypt $encryption 0 R /ID [<" . bin2hex( $id[0] ) . '> <' . bin2hex( $id[1] ) . "> ] >>\nstartxref\n$xref\n%%EOF\n";
        return $out;
    }

    private function xref_table( $positions, $size ) {
        $free = array();
        for ( $num = 0; $num < $size; $num++ ) {
            if ( ! isset( $positions[ $num ] ) ) {
                $free[] = $num;
            }
        }
        $next = array();
        foreach ( $free as $index => $num ) {
            $next[ $num ] = $free[ $index + 1 ] ?? 0;
        }
        $table = "xref\n0 $size\n";
        for ( $num = 0; $num < $size; $num++ ) {
            if ( isset( $positions[ $num ] ) ) {
                $table .= sprintf( "%010d %05d n \n", $positions[ $num ], $this->offsets[ $num ][1] ?? 0 );
            } else {
                $table .= sprintf( "%010d %05d f \n", $next[ $num ], 0 === $num ? 65535 : ( $this->free[ $num ] ?? 0 ) );
            }
        }
        return $table;
    }

    private function file_id( $node ) {
        $id = $node && 'arr' === $node['t'] && 2 === count( $node['v'] ) ? $node['v'] : array();
        if ( ! $id || 'str' !== $id[0]['t'] || 'str' !== $id[1]['t'] || '' === $id[0]['v'] || '' === $id[1]['v'] ) {
            $random = random_bytes( 16 );
            return array( $random, $random );
        }
        return array( $id[0]['v'], $id[1]['v'] );
    }

    private static function with_extensions( $catalog ) {
        $adbe = self::dict( array(
            'BaseVersion'    => array( 't' => 'name', 'raw' => '/1.7', 'name' => '1.7' ),
            'ExtensionLevel' => array( 't' => 'raw', 'v' => '8' ),
        ) );
        $extensions = self::get( $catalog, 'Extensions' );
        if ( null === $extensions ) {
            return self::with( $catalog, 'Extensions', self::dict( array( 'ADBE' => $adbe ) ) );
        }
        if ( 'dict' !== $extensions['t'] ) {
            throw self::error( 'The catalog has indirect /Extensions.' );
        }
        return self::with( $catalog, 'Extensions', self::with( $extensions, 'ADBE', $adbe ) );
    }

    private static function name( $name ) {
        return array( 't' => 'name', 'raw' => '/' . $name, 'name' => $name );
    }

    private static function dict( $entries ) {
        $dict = array( 't' => 'dict', 'v' => array() );
        foreach ( $entries as $key => $value ) {
            $dict['v'][] = array( self::name( $key ), $value );
        }
        return $dict;
    }

    private static function get( $dict, $key ) {
        foreach ( $dict['v'] as $entry ) {
            if ( $entry[0]['name'] === $key ) {
                return $entry[1];
            }
        }
        return null;
    }

    private static function with( $dict, $key, $value ) {
        foreach ( $dict['v'] as $index => $entry ) {
            if ( $entry[0]['name'] === $key ) {
                $dict['v'][ $index ][1] = $value;
                return $dict;
            }
        }
        $dict['v'][] = array( self::name( $key ), $value );
        return $dict;
    }

    private static function number( $node ) {
        return $node && 'raw' === $node['t'] && 1 === preg_match( '/^\d+$/', $node['v'] ) ? (int) $node['v'] : null;
    }

    private static function name_of( $node ) {
        return $node && 'name' === $node['t'] ? $node['name'] : null;
    }

    private function read_xref() {
        if ( ! preg_match( '/startxref\s+(\d+)\s+%%EOF\s*$/', substr( $this->pdf, -1024 ), $match ) ) {
            throw self::error( 'No startxref at the end of the file.' );
        }
        $this->pos = (int) $match[1];
        if ( 'xref' !== substr( $this->pdf, $this->pos, 4 ) ) {
            throw self::error( 'Cross-reference streams are not supported.' );
        }
        $this->pos += 4;
        while ( true ) {
            $this->skip_space();
            if ( 'trailer' === substr( $this->pdf, $this->pos, 7 ) ) {
                $this->pos += 7;
                break;
            }
            if ( ! preg_match( '/\G(\d+) +(\d+)[ \t]*(?:\r\n|\r|\n)/', $this->pdf, $section, 0, $this->pos ) ) {
                throw self::error( 'Malformed xref table.' );
            }
            $this->pos += strlen( $section[0] );
            $start = (int) $section[1];
            $count = (int) $section[2];
            if ( $start + $count > self::MAX_OBJECTS ) {
                throw self::error( 'Too many objects.' );
            }
            for ( $num = $start; $num < $start + $count; $num++ ) {
                if ( ! preg_match( '/\G(\d{10}) (\d{5}) ([fn])[ \r\n]{1,2}/', $this->pdf, $entry, 0, $this->pos ) ) {
                    throw self::error( 'Malformed xref entry.' );
                }
                $this->pos += strlen( $entry[0] );
                if ( isset( $this->offsets[ $num ] ) || isset( $this->free[ $num ] ) ) {
                    throw self::error( sprintf( 'Object %d is listed twice.', (int) $num ) );
                }
                if ( 'n' === $entry[3] ) {
                    $this->offsets[ $num ] = array( (int) $entry[1], (int) $entry[2] );
                } else {
                    $this->free[ $num ] = (int) $entry[2];
                }
            }
        }
        unset( $this->offsets[0] );
        if ( ! $this->offsets ) {
            throw self::error( 'The xref table is empty.' );
        }
        $trailer = $this->parse_value( 0 );
        if ( 'dict' !== $trailer['t'] ) {
            throw self::error( 'The trailer is not a dictionary.' );
        }
        return $trailer;
    }

    private function read_header( $num, $gen, $offset ) {
        if ( $offset >= strlen( $this->pdf ) || ! preg_match( '/\G(\d+)\s+(\d+)\s+obj/', $this->pdf, $header, 0, $offset ) || (int) $header[1] !== $num || (int) $header[2] !== $gen ) {
            throw self::error( sprintf( 'Object %d is not at its xref offset.', (int) $num ) );
        }
        $this->pos = $offset + strlen( $header[0] );
    }

    private function read_object( $num, $gen, $offset ) {
        $this->read_header( $num, $gen, $offset );
        $value = $this->parse_value( 0 );
        $keyword = $this->keyword();
        if ( 'endobj' === $keyword ) {
            return array( $value, null );
        }
        if ( 'stream' !== $keyword || 'dict' !== $value['t'] ) {
            throw self::error( sprintf( 'Object %d has no endobj.', (int) $num ) );
        }
        if ( in_array( self::name_of( self::get( $value, 'Type' ) ), array( 'XRef', 'ObjStm' ), true ) ) {
            throw self::error( 'Object and cross-reference streams are not supported.' );
        }
        if ( "\r\n" === substr( $this->pdf, $this->pos, 2 ) ) {
            $this->pos += 2;
        } elseif ( "\n" === substr( $this->pdf, $this->pos, 1 ) ) {
            $this->pos++;
        } else {
            throw self::error( sprintf( 'Object %d has no end of line after stream.', (int) $num ) );
        }
        $length = $this->stream_length( self::get( $value, 'Length' ) );
        if ( $this->pos + $length > strlen( $this->pdf ) ) {
            throw self::error( sprintf( 'The stream of object %d is cut off.', (int) $num ) );
        }
        $data = substr( $this->pdf, $this->pos, $length );
        $this->pos += $length;
        if ( 'endstream' !== $this->keyword() || 'endobj' !== $this->keyword() ) {
            throw self::error( sprintf( 'The /Length of object %d does not match its stream.', (int) $num ) );
        }
        return array( $value, $data );
    }

    private function stream_length( $node ) {
        $length = self::number( $node );
        if ( null !== $length ) {
            return $length;
        }
        if ( ! $node || 'ref' !== $node['t'] || ! isset( $this->offsets[ $node['n'] ] ) || $this->offsets[ $node['n'] ][1] !== $node['g'] ) {
            throw self::error( 'A stream has no valid /Length.' );
        }
        $num = $node['n'];
        if ( ! isset( $this->lengths[ $num ] ) ) {
            $pos = $this->pos;
            $this->read_header( $num, $node['g'], $this->offsets[ $num ][0] );
            $length = self::number( $this->parse_value( 0 ) );
            if ( null === $length || 'endobj' !== $this->keyword() ) {
                throw self::error( 'A stream has no valid /Length.' );
            }
            $this->lengths[ $num ] = $length;
            $this->pos = $pos;
        }
        return $this->lengths[ $num ];
    }

    private function skip_space() {
        $length = strlen( $this->pdf );
        while ( $this->pos < $length ) {
            $this->pos += strspn( $this->pdf, self::WHITESPACE, $this->pos );
            if ( '%' !== substr( $this->pdf, $this->pos, 1 ) ) {
                return;
            }
            $this->pos += strcspn( $this->pdf, "\r\n", $this->pos );
        }
    }

    private function regular() {
        $length = strcspn( $this->pdf, self::WHITESPACE . self::DELIMITERS, $this->pos );
        $token = substr( $this->pdf, $this->pos, $length );
        $this->pos += $length;
        return $token;
    }

    private function keyword() {
        $this->skip_space();
        return $this->regular();
    }

    private function parse_value( $depth ) {
        if ( $depth > self::MAX_DEPTH ) {
            throw self::error( 'Objects are nested too deep.' );
        }
        $this->skip_space();
        $char = substr( $this->pdf, $this->pos, 1 );
        if ( '<' === $char ) {
            return '<<' === substr( $this->pdf, $this->pos, 2 ) ? $this->parse_dict( $depth ) : $this->parse_hex();
        }
        if ( '(' === $char ) {
            return $this->parse_literal();
        }
        if ( '[' === $char ) {
            return $this->parse_array( $depth );
        }
        if ( '/' === $char ) {
            return $this->parse_name();
        }
        $token = $this->regular();
        if ( '' === $token ) {
            throw self::error( '' === $char ? 'Unexpected end of file.' : sprintf( 'Unexpected "%s" at byte %d.', esc_html( $char ), (int) $this->pos ) );
        }
        if ( in_array( $token, self::RESERVED, true ) ) {
            throw self::error( sprintf( 'Unexpected "%s" at byte %d.', esc_html( $token ), (int) $this->pos ) );
        }
        if ( ctype_digit( $token ) ) {
            $pos = $this->pos;
            $gen = $this->keyword();
            if ( ctype_digit( $gen ) && 'R' === $this->keyword() ) {
                return array( 't' => 'ref', 'n' => (int) $token, 'g' => (int) $gen );
            }
            $this->pos = $pos;
        }
        return array( 't' => 'raw', 'v' => $token );
    }

    private function parse_dict( $depth ) {
        $this->pos += 2;
        $entries = array();
        while ( true ) {
            $this->skip_space();
            if ( '>>' === substr( $this->pdf, $this->pos, 2 ) ) {
                $this->pos += 2;
                return array( 't' => 'dict', 'v' => $entries );
            }
            if ( '/' !== substr( $this->pdf, $this->pos, 1 ) ) {
                throw self::error( sprintf( 'Dictionary key expected at byte %d.', (int) $this->pos ) );
            }
            $key = $this->parse_name();
            $entries[] = array( $key, $this->parse_value( $depth + 1 ) );
        }
    }

    private function parse_array( $depth ) {
        $this->pos++;
        $items = array();
        while ( true ) {
            $this->skip_space();
            if ( ']' === substr( $this->pdf, $this->pos, 1 ) ) {
                $this->pos++;
                return array( 't' => 'arr', 'v' => $items );
            }
            $items[] = $this->parse_value( $depth + 1 );
        }
    }

    private function parse_name() {
        $this->pos++;
        $raw = $this->regular();
        $name = preg_replace_callback( '/#([0-9A-Fa-f]{2})/', function ( $match ) {
            return chr( hexdec( $match[1] ) );
        }, $raw );
        return array( 't' => 'name', 'raw' => '/' . $raw, 'name' => $name );
    }

    private function parse_hex() {
        $end = strpos( $this->pdf, '>', $this->pos );
        if ( false === $end ) {
            throw self::error( 'Unterminated hex string.' );
        }
        $hex = str_replace( str_split( self::WHITESPACE ), '', substr( $this->pdf, $this->pos + 1, $end - $this->pos - 1 ) );
        if ( '' !== $hex && ! ctype_xdigit( $hex ) ) {
            throw self::error( sprintf( 'Invalid hex string at byte %d.', (int) $this->pos ) );
        }
        $this->pos = $end + 1;
        return array( 't' => 'str', 'v' => (string) hex2bin( strlen( $hex ) % 2 ? $hex . '0' : $hex ) );
    }

    private function parse_literal() {
        $start = $this->pos++;
        $length = strlen( $this->pdf );
        $depth = 1;
        $value = '';
        while ( $this->pos < $length ) {
            $run = strcspn( $this->pdf, "\\()\r", $this->pos );
            $value .= substr( $this->pdf, $this->pos, $run );
            $this->pos += $run;
            if ( $this->pos >= $length ) {
                break;
            }
            $char = $this->pdf[ $this->pos++ ];
            if ( '(' === $char ) {
                $depth++;
                $value .= $char;
            } elseif ( ')' === $char ) {
                if ( 0 === --$depth ) {
                    return array( 't' => 'str', 'v' => $value );
                }
                $value .= $char;
            } elseif ( "\r" === $char ) {
                $value .= "\n";
                if ( "\n" === substr( $this->pdf, $this->pos, 1 ) ) {
                    $this->pos++;
                }
            } else {
                $value .= $this->parse_escape();
            }
        }
        throw self::error( sprintf( 'Unterminated string at byte %d.', (int) $start ) );
    }

    private function parse_escape() {
        $char = substr( $this->pdf, $this->pos++, 1 );
        if ( isset( self::ESCAPES[ $char ] ) ) {
            return self::ESCAPES[ $char ];
        }
        if ( "\r" === $char ) {
            if ( "\n" === substr( $this->pdf, $this->pos, 1 ) ) {
                $this->pos++;
            }
            return '';
        }
        if ( "\n" === $char ) {
            return '';
        }
        if ( $char >= '0' && $char <= '7' ) {
            $octal = $char . substr( $this->pdf, $this->pos, strspn( $this->pdf, '01234567', $this->pos, 2 ) );
            $this->pos += strlen( $octal ) - 1;
            return chr( octdec( $octal ) & 0xFF );
        }
        return $char;
    }

    private function serialize( $node ) {
        switch ( $node['t'] ) {
            case 'dict':
                $entries = array();
                foreach ( $node['v'] as $entry ) {
                    $entries[] = $entry[0]['raw'] . ' ' . $this->serialize( $entry[1] );
                }
                return '<<' . implode( ' ', $entries ) . '>>';
            case 'arr':
                $items = array();
                foreach ( $node['v'] as $item ) {
                    $items[] = $this->serialize( $item );
                }
                return '[' . implode( ' ', $items ) . ']';
            case 'str':
                return '<' . bin2hex( $this->encrypt->encrypt( $node['v'] ) ) . '>';
            case 'name':
                return $node['raw'];
            case 'ref':
                return $node['n'] . ' ' . $node['g'] . ' R';
            default:
                return $node['v'];
        }
    }
}
