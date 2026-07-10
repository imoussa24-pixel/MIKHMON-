<?php
/*
 * Writable configuration store for Mikhmon/TIKRAS.
 * The application folder may live in Windows Documents, where PHP can be
 * blocked from writing. User changes are therefore persisted in AppData.
 */
include_once(dirname(__FILE__) . '/tikras_core.php');

if (!function_exists('tikras_config_local_dir')) {
  function tikras_config_local_dir()
  {
    $base = getenv('LOCALAPPDATA');
    if ($base === false || trim((string) $base) == '') {
      $base = sys_get_temp_dir();
    }
    return rtrim((string) $base, "\\/") . DIRECTORY_SEPARATOR . 'MikhmonProAdmin';
  }
}

if (!function_exists('tikras_config_local_path')) {
  function tikras_config_local_path()
  {
    $path = getenv('TIKRAS_CONFIG_PATH');
    if ($path !== false && trim((string) $path) != '') {
      return (string) $path;
    }
    return tikras_config_local_dir() . DIRECTORY_SEPARATOR . 'config.local.php';
  }
}

if (!function_exists('tikras_quickbt_local_path')) {
  function tikras_quickbt_local_path()
  {
    return tikras_config_local_dir() . DIRECTORY_SEPARATOR . 'quickbt.local.php';
  }
}

if (!function_exists('tikras_config_read_local')) {
  function tikras_config_read_local()
  {
    $path = tikras_config_local_path();
    if (!is_file($path)) {
      return null;
    }
    $data = include($path);
    return is_array($data) ? $data : null;
  }
}

if (!function_exists('tikras_config_apply_local')) {
  function tikras_config_apply_local(&$data)
  {
    $local = tikras_config_read_local();
    if (is_array($local)) {
      $data = $local;
    }
  }
}

if (!function_exists('tikras_config_write_all')) {
  function tikras_config_write_all($data)
  {
    if (!is_array($data)) {
      return false;
    }

    $dir = tikras_config_local_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
      return false;
    }

    $path = tikras_config_local_path();
    $content = "<?php\nreturn " . var_export($data, true) . ";\n";
    $tmp = $path . '.tmp-' . str_replace('.', '-', uniqid('', true));
    $written = file_put_contents($tmp, $content);
    if ($written === false) {
      return false;
    }

    if (@rename($tmp, $path)) {
      if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
      }
      return true;
    }

    @unlink($path);
    if (@rename($tmp, $path)) {
      if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
      }
      return true;
    }

    $written = file_put_contents($path, $content);
    @unlink($tmp);
    if ($written !== false && function_exists('opcache_invalidate')) {
      @opcache_invalidate($path, true);
    }
    return $written !== false;
  }
}

if (!function_exists('tikras_quickbt_read')) {
  function tikras_quickbt_read()
  {
    $path = tikras_quickbt_local_path();
    if (!is_file($path)) {
      return null;
    }
    $data = include($path);
    if (!is_array($data) || !isset($data['qrbt'])) {
      return null;
    }
    return $data['qrbt'] == 'enable' ? 'enable' : 'disable';
  }
}

if (!function_exists('tikras_quickbt_write')) {
  function tikras_quickbt_write($qrbt)
  {
    $dir = tikras_config_local_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
      return false;
    }
    $path = tikras_quickbt_local_path();
    $value = $qrbt == 'enable' ? 'enable' : 'disable';
    $content = "<?php\nreturn array('qrbt' => " . var_export($value, true) . ");\n";
    $written = file_put_contents($path, $content);
    if ($written !== false && function_exists('opcache_invalidate')) {
      @opcache_invalidate($path, true);
    }
    return $written !== false;
  }
}
?>
