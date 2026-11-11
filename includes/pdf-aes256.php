<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Standard security handler, AES-256 revision 6: ISO 32000-2, 7.6.4.3.3, Algorithms 2.B, 8, 9 and 10.
class SurveyJS_Pro_Pdf_Aes256 {

    const PERMISSIONS = -4;
    const MAX_PASSWORD_BYTES = 127;
    const HASHES = array( 'sha256', 'sha384', 'sha512' );

    private $key;
    private $dictionary;

    public function __construct( $user_password, $owner_password ) {
        $this->key = random_bytes( 32 );
        list( $u, $ue ) = $this->password_entries( $user_password, '' );
        list( $o, $oe ) = $this->password_entries( $owner_password, $u );
        $perms = self::aes( 'aes-256-ecb', $this->key, pack( 'V', self::PERMISSIONS ) . "\xFF\xFF\xFF\xFFTadb" . random_bytes( 4 ), '' );

        $this->dictionary = '<< /Filter /Standard /V 5 /R 6 /Length 256'
            . ' /CF << /StdCF << /Type /CryptFilter /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >> /StmF /StdCF /StrF /StdCF'
            . ' /O <' . bin2hex( $o ) . '> /U <' . bin2hex( $u ) . '> /OE <' . bin2hex( $oe ) . '> /UE <' . bin2hex( $ue ) . '>'
            . ' /Perms <' . bin2hex( $perms ) . '> /P ' . self::PERMISSIONS . ' /EncryptMetadata true >>';
    }

    public function dictionary() {
        return $this->dictionary;
    }

    public function encrypt( $data ) {
        $iv = random_bytes( 16 );
        $cipher = openssl_encrypt( (string) $data, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv );
        if ( false === $cipher ) {
            throw new RuntimeException( 'AES-256 encryption failed.' );
        }
        return $iv . $cipher;
    }

    private function password_entries( $password, $user_entry ) {
        $password = substr( (string) $password, 0, self::MAX_PASSWORD_BYTES );
        $validation_salt = random_bytes( 8 );
        $key_salt = random_bytes( 8 );
        $entry = self::hash( $password, $validation_salt, $user_entry ) . $validation_salt . $key_salt;
        $encrypted_key = self::aes( 'aes-256-cbc', self::hash( $password, $key_salt, $user_entry ), $this->key, str_repeat( "\0", 16 ) );
        return array( $entry, $encrypted_key );
    }

    private static function aes( $method, $key, $data, $iv ) {
        $cipher = openssl_encrypt( $data, $method, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv );
        if ( false === $cipher ) {
            throw new RuntimeException( 'AES encryption failed.' );
        }
        return $cipher;
    }

    private static function hash( $password, $salt, $user_entry ) {
        $k = hash( 'sha256', $password . $salt . $user_entry, true );
        for ( $round = 0; ; $round++ ) {
            $e = self::aes( 'aes-128-cbc', substr( $k, 0, 16 ), str_repeat( $password . $k . $user_entry, 64 ), substr( $k, 16, 16 ) );
            $k = hash( self::HASHES[ array_sum( unpack( 'C16', $e ) ) % 3 ], $e, true );
            if ( $round >= 63 && ord( $e[ strlen( $e ) - 1 ] ) <= $round - 31 ) {
                return substr( $k, 0, 32 );
            }
        }
    }
}
