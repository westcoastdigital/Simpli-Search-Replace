<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SSR_Serializer {

    public function recursive_replace( $search, $replace, $data, $case_sensitive = false ) {

        if ( is_serialized( $data ) ) {
            $unserialized = maybe_unserialize( $data );
            $unserialized = $this->recursive_replace( $search, $replace, $unserialized, $case_sensitive );
            return maybe_serialize( $unserialized );
        }

        if ( is_array( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data[ $key ] = $this->recursive_replace( $search, $replace, $value, $case_sensitive );
            }
            return $data;
        }

        if ( is_object( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data->$key = $this->recursive_replace( $search, $replace, $value, $case_sensitive );
            }
            return $data;
        }

        if ( is_string( $data ) ) {
            if ( $case_sensitive ) {
                return str_replace( $search, $replace, $data );
            } else {
                return str_ireplace( $search, $replace, $data );
            }
        }

        return $data;
    }
}
