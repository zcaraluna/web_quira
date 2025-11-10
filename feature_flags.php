<?php
/**
 * Gestión de banderas de características (feature flags) simples basadas en archivo JSON
 */

define('FEATURE_FLAGS_FILE', __DIR__ . '/feature_flags.json');

$FEATURE_FLAGS_CACHE = null;

function loadFeatureFlags(): array {
    global $FEATURE_FLAGS_CACHE;

    if ($FEATURE_FLAGS_CACHE !== null) {
        return $FEATURE_FLAGS_CACHE;
    }

    if (!file_exists(FEATURE_FLAGS_FILE)) {
        $FEATURE_FLAGS_CACHE = [];
        return $FEATURE_FLAGS_CACHE;
    }

    $raw = file_get_contents(FEATURE_FLAGS_FILE);
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        $FEATURE_FLAGS_CACHE = [];
        return $FEATURE_FLAGS_CACHE;
    }

    $FEATURE_FLAGS_CACHE = $data;
    return $FEATURE_FLAGS_CACHE;
}

function getFeatureFlag(string $key, $default = true) {
    $flags = loadFeatureFlags();
    return array_key_exists($key, $flags) ? $flags[$key] : $default;
}

function setFeatureFlag(string $key, $value): bool {
    global $FEATURE_FLAGS_CACHE;

    $flags = loadFeatureFlags();
    $flags[$key] = (bool)$value;

    $json = json_encode($flags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (file_put_contents(FEATURE_FLAGS_FILE, $json, LOCK_EX) === false) {
        return false;
    }

    clearstatcache(true, FEATURE_FLAGS_FILE);

    // Reset cache
    $FEATURE_FLAGS_CACHE = null;

    return true;
}

