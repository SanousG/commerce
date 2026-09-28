<?php

return [
    'environment' => env('SUNAT_ENVIRONMENT', 'BETA'),
    'production_enabled' => (bool) env('SUNAT_PRODUCTION_ENABLED', false),
    'wsdl' => [
        'BETA' => env('SUNAT_BETA_WSDL', 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService?wsdl'),
        'PRODUCTION' => env('SUNAT_PRODUCTION_WSDL', 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService?wsdl'),
        'QUERY' => env('SUNAT_QUERY_WSDL', 'https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService?wsdl'),
    ],
    'credentials' => [
        'username' => env('SUNAT_USERNAME'),
        'password' => env('SUNAT_PASSWORD'),
    ],
    'certificate' => [
        'path' => env('SUNAT_CERTIFICATE_PATH'),
        'password' => env('SUNAT_CERTIFICATE_PASSWORD'),
    ],
    'storage_disk' => env('SUNAT_STORAGE_DISK', 'sunat'),
    'xsd_root' => env('SUNAT_XSD_ROOT', 'provisioned/xsd'),
    'xsd_invoice' => env('SUNAT_XSD_INVOICE', 'provisioned/xsd/Archivos XSD/2.1/maindoc/UBL-Invoice-2.1.xsd'),
    'max_cdr_bytes' => (int) env('SUNAT_MAX_CDR_BYTES', 10 * 1024 * 1024),
    'soap' => [
        'connection_timeout' => (int) env('SUNAT_CONNECTION_TIMEOUT', 10),
        'trace' => false,
    ],
];
