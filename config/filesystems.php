<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Default Filesystem Disk
  |--------------------------------------------------------------------------
  |
  | Here you may specify the default filesystem disk that should be used
  | by the framework. The "local" disk, as well as a variety of cloud
  | based disks are available to your application. Just store away!
  |
  */

  'default' => env('FILESYSTEM_DRIVER', 'local'),

  // Product receipts and invoices contain personal and financial data and must
  // never use the public disk. Keep this on a private-capable disk.
  'product_order_documents_disk' => env('PRODUCT_ORDER_DOCUMENT_DISK', 'local'),

  // Ticket invoices and offline-payment evidence contain personal and financial
  // data and must only be served through authenticated, ownership-scoped routes.
  'event_booking_documents_disk' => env('EVENT_BOOKING_DOCUMENT_DISK', 'local'),

  // Registration imports contain attendee PII and must never be web-accessible.
  'event_registration_import_disk' => env('EVENT_REGISTRATION_IMPORT_DISK', 'local'),

  // Documents submitted through corporate registration forms contain attendee
  // PII and are served only through owner-authorized download endpoints.
  'event_registration_upload_disk' => env('EVENT_REGISTRATION_UPLOAD_DISK', 'local'),

  /*
  |--------------------------------------------------------------------------
  | Filesystem Disks
  |--------------------------------------------------------------------------
  |
  | Here you may configure as many filesystem "disks" as you wish, and you
  | may even configure multiple disks of the same driver. Defaults have
  | been setup for each driver as an example of the required options.
  |
  | Supported Drivers: "local", "ftp", "sftp", "s3"
  |
  */

  'disks' => [

    'local' => [
      'driver' => 'local',
      'root' => storage_path('app'),
    ],

    'public' => [
      'driver' => 'local',
      'root' => storage_path('app/public'),
      'url' => env('APP_URL') . '/storage',
      'visibility' => 'public',
    ],

    's3' => [
      'driver' => 's3',
      'key' => env('AWS_ACCESS_KEY_ID'),
      'secret' => env('AWS_SECRET_ACCESS_KEY'),
      'region' => env('AWS_DEFAULT_REGION'),
      'bucket' => env('AWS_BUCKET'),
      'url' => env('AWS_URL'),
      'endpoint' => env('AWS_ENDPOINT'),
      'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
      'throw' => true,
    ],

  ],

  /*
  |--------------------------------------------------------------------------
  | Symbolic Links
  |--------------------------------------------------------------------------
  |
  | Here you may configure the symbolic links that will be created when the
  | `storage:link` Artisan command is executed. The array keys should be
  | the locations of the links and the values should be their targets.
  |
  */

  'links' => [
    public_path('storage') => storage_path('app/public'),
  ],

];
