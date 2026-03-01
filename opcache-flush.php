<?php
// Temporary file used by deploy workflow to flush OPcache in the web SAPI
// Auto-deleted after use by the deploy script
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'OPcache flushed';
} else {
    echo 'OPcache not available';
}
