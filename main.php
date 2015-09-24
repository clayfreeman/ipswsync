<?php
  /////////////////////////// BEGIN ADVANCED CONFIG //////////////////////////

  // The URL at which to find IPSW information
  $url    = 'http://ax.phobos.apple.com.edgesuite.net/WebObjects/MZStore.woa'.
            '/wa/com.apple.jingle.appserver.client.MZITunesClientCheck/version';

  //////////////////////////////// END CONFIG ////////////////////////////////

  // Load the configuration file
  require_once(__DIR__.'/config.php');

  // Verify that the given path is a writable directory
  if (!isset($path) || !is_dir($path) || !is_writable($path)) {
    echo 'Please configure the \'$path\' variable in \'config.php\' to a '.
         "writable directory.\n";
    exit(1);
  }

  // Require composer's vendor autoload
  require_once(__DIR__.'/vendor/autoload.php');
  // Instantiate a new Filesystem object
  $fs = new \Symfony\Component\Filesystem\Filesystem;

  // Check if an instance of this script is already running
  if (file_exists($path.'/sync.lock')) exit(0);
  // Lock the syncrhonization directory
  touch($path.'/sync.lock') or exit(1);

  // The regular expression to match IPSW URLs
  $regex  = '(?P<url>.*\\/(?P<device>(?P<category>[a-z]+)\\d+,\\d+)_'.
            '(?P<version>[\\d.]+)_[a-z0-9]+_Restore.ipsw)';
  // The regular expression to match IPSW URLs in a Property List
  $sregex = '<string>'.$regex;
  // Prepare the regular expression strings for use with preg_match(...)
  $sregex = '/'.$sregex.'/i';
  $regex  = '/'.$regex .'/i';

  // Split the above information resource by newline character
  $urls   = explode("\n", str_ireplace("\r", null, trim(
            file_get_contents($url))));
  // An array to hold IPSW information
  $ipsw   = array();
  // An array to hold all encountered version numbers
  $vers   = array();
  // Iterate over each line of the information resource
  foreach ($urls as $url) {
    // Check that this line matches the above regular expression for Property
    // List file format
    if (preg_match($sregex, trim($url), $m)) {
      // Add the version for this URL to the version array
      if (!in_array($m['version'], $vers))
        $vers[] = $m['version'];
      // Lowercase the category in order to keep the directory hierarchy all
      // lowercase (except IPSW files)
      $m['category'] = strtolower($m['category']);
      // Create an entry for this category if it doesn't exist
      if (!isset($ipsw[$m['category']]))
        $ipsw[$m['category']] = array();
      // Add the URL for this IPSW to the IPSW array for further processing
      $ipsw[$m['category']][$m['version']][] = $m['url'];
      // Sort the arrays using natural sort order
      ksort($ipsw[$m['category']], SORT_NATURAL);
      ksort($ipsw, SORT_NATURAL);
      sort($vers, SORT_NATURAL);
    }
  }

  // Pop the last item (newest version) off the end of the array
  $vers = array_pop($vers);
  foreach ($ipsw as $category => $versions) {
    foreach ($versions as $version => $urls) {
      // Remove each version that doesn't correspond with the version in $vers
      // or has no URL entries
      if ($version != $vers || count($urls) < 1)
        unset($ipsw[$category][$version]);
    }
    // Remove categories that have no version information
    if (count($versions) < 1)
      unset($ipsw[$category]);
  }

  // Get a list of categories currently on-disk
  $categories = array_map('basename', array_filter(glob($path.'/*'), 'is_dir'));
  foreach ($categories as $category) {
    // If the on-disk category doesn't exist according to the IPSW information
    // resource, remove it
    if (!isset($ipsw[$category])) {
      $remove = $path.'/'.$category;
      echo "Removing ".escapeshellarg($remove)." ...\n";
      $fs->remove($remove);
    }
    else {
      // Get a list of versions in this on-disk category
      $versions = array_map('basename', array_filter(glob($path.'/'.$category.
                  '/*'), 'is_dir'));
      foreach ($versions as $version) {
        // If the on-disk version doesn't exist according to the IPSW
        // information resource, remove it
        if (!isset($ipsw[$category][$version])) {
          $remove = $path.'/'.$category.'/'.$version;
          echo "Removing ".escapeshellarg($remove)." ...\n";
          $fs->remove($remove);
        }
        else {
          // Get a list of files in this on-disk version
          $files = array_map('basename', array_filter(glob($path.'/'.$category.
                   '/'.$version.'/*'), 'is_file'));
          // Get a compatible comparison list of files according to the IPSW
          // information resource
          $links = array_map('basename', $ipsw[$category][$version]);
          foreach ($files as $file) {
            // If the on-disk file doesn't exist according to the IPSW
            // information resource, remove it
            if (!in_array($file, $links)) {
              $remove = $path.'/'.$category.'/'.$version.'/'.$file;
              echo "Removing ".escapeshellarg($remove)." ...\n";
              $fs->remove($remove);
            }
          }
        }
      }
    }
  }

  foreach ($ipsw as $category => $versions) {
    // If a category from the IPSW information resource doesn't exist on-disk,
    // create it
    if (!in_array($category, $categories)) {
      $newdir = $path.'/'.$category;
      echo "Creating directory ".escapeshellarg($newdir)." ...\n";
      mkdir($newdir, 0755);
    }

    // Get a list of versions in this on-disk category
    $versions = array_map('basename', array_filter(glob($path.'/'.$category.
                '/*'), 'is_dir'));
    foreach ($ipsw[$category] as $version => $urls) {
      // If a version from the IPSW information resource doesn't exist on-disk,
      // create it
      if (!in_array($version, $versions)) {
        $newdir = $path.'/'.$category.'/'.$version;
        echo "Creating directory ".escapeshellarg($newdir)." ...\n";
        mkdir($newdir, 0755);
      }

      // Get a list of files in this on-disk version
      $files = array_map('basename', array_filter(glob($path.'/'.$category.
               '/'.$version.'/*'), 'is_file'));
      // Get a compatible comparison list of files according to the IPSW
      // information resource
      $links = array_map('basename', $ipsw[$category][$version]);
      foreach ($links as $key => $link) {
        // If a file from the IPSW information resource doesn't exist on-disk,
        // download it
        if (!in_array($link, $files)) {
          $durl = $ipsw[$category][$version][$key];
          $dest = $path.'/'.$category.'/'.$version.'/'.$link;
          echo "Downloading IPSW ".escapeshellarg($durl)." to ".
               escapeshellarg($dest)." ...\n";
          passthru('curl '.escapeshellarg($durl).' > '.escapeshellarg($dest));
        }
      }
    }
  }

  // Remove the synchronization directory lock
  unlink($path.'/sync.lock');

  echo "IPSW synchronization complete.\n";
