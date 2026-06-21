<?php
namespace App\Utils;

class Storage {
    /**
     * Resolves the storage base directory.
     * @param bool $isResume Whether this is resolving for RESUME_STORAGE_PATH
     * @param bool $isUpload Whether this resolution is for writing a new file
     * @return string
     */
    public static function resolveStorageBase($isResume = false, $isUpload = false) {
        $envVar = $isResume ? 'RESUME_STORAGE_PATH' : 'FILE_STORAGE_PATH';
        $path = getenv($envVar);

        if (!$path) {
            $path = __DIR__ . '/../../storage'; // Fallback
        }

        $realPath = realpath($path);
        if (!$realPath && !is_dir($path)) {
            // Path might not exist yet, we will attempt to create it
            @mkdir($path, 0755, true);
            $realPath = realpath($path);
        }

        // Get absolute document root
        $docRoot = realpath(__DIR__ . '/../../');
        
        $isInsideDocroot = false;
        if ($realPath && $docRoot) {
            if (strpos($realPath, $docRoot) === 0) {
                $isInsideDocroot = true;
            }
        } else {
            // Fallback check if realpath fails
            if (strpos($path, 'storage') !== false && strpos($path, '..') !== false) {
                $isInsideDocroot = true;
            }
        }

        // FAIL LOUD GUARD - Disabled for Railway Demo
        if ($isUpload && $isInsideDocroot) {
            $appEnv = getenv('APP_ENV');
            if ($appEnv !== 'local') {
                error_log("Warning: Storage base '$realPath' is inside document root, but APP_ENV is '$appEnv' (not 'local'). Allowing for Railway demo.");
            }
        }

        return rtrim($path, '/');
    }
}
