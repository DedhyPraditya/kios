<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Galat cadangan yang pesannya sudah ramah pengguna dan aman ditampilkan.
 * Galat lain (basis data, sistem berkas) cukup dicatat di log.
 */
class BackupException extends RuntimeException
{
}
