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

  // Verify that the last_versions variable has been set to an acceptable value
  if (!isset($last_versions) || $last_versions < 1) {
    echo 'Please configure the \'$last_versions\' variable in \'config.php\' '.
         "to a positive value.\n";
    exit(1);
  }

  // Verify that the parallel variable has been set to an acceptable value
  if (!isset($parallel) || $parallel < 1 || $parallel > 6) {
    echo 'Please configure the \'$parallel\' variable in \'config.php\' '.
         "to a value from 1 to 6.\n";
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
  $regex  = '/'.$regex .'/i';
  $sregex = '/'.$sregex.'/i';

  // Split the above information resource by newline character
  $urls   = file_get_contents($url);
  // An array to hold IPSW information
  $ipsw   = array();
  // An array to hold all encountered version numbers
  $vers   = array();
  // Create an array of matches from the information resource
  preg_match_all($sregex, $urls, $matches, PREG_SET_ORDER);
  // Iterate over each line of the information resource
  foreach ($matches as $m) {
    // Lowercase the category in order to keep the directory hierarchy all
    // lowercase (except IPSW files)
    $m['category'] = strtolower($m['category']);
    // Add the version for this URL to the version array
    if (!isset($vers[$m['category']]))
      $vers[$m['category']] = array();
    // Add the version for this URL to the version array
    if (!in_array($m['version'], $vers[$m['category']]))
      $vers[$m['category']][] = $m['version'];
    // Create an entry for this category if it doesn't exist
    if (!isset($ipsw[$m['category']]))
      $ipsw[$m['category']] = array();
    // Create an entry for this version if it doesn't exist
    if (!isset($ipsw[$m['category']][$m['version']]))
      $ipsw[$m['category']][$m['version']] = array();
    // Add the URL for this IPSW to the IPSW array for further processing
    if (!in_array($m['url'], $ipsw[$m['category']][$m['version']]))
      $ipsw[$m['category']][$m['version']][] = $m['url'];
  }

  // Sort the resulting IPSW and version arrays
  ksort($vers, SORT_NATURAL);
  ksort($ipsw, SORT_NATURAL);
  foreach ($vers as $category => $versions)
    sort($vers[$category], SORT_NATURAL);
  foreach ($ipsw as $category => $versions)
    ksort($ipsw[$category], SORT_NATURAL);

  // Iterate over each category to select acceptable versions
  foreach ($vers as $category => $versions) {
    // Pick a non-negative start value to select versions
    $start = (count($versions) - $last_versions);
    $start = ($start >= 0 ? $start : 0);
    // Create an array to store acceptable versions
    $v = array();
    // Select acceptable versions and place them in the array
    for ($i = $start; $i < count($versions); $i++) {
      $v[] = $versions[$i];
    }
    // Remove unacceptable versions from the IPSW array
    foreach ($ipsw[$category] as $version => $urls) {
      if (!in_array($version, $v))
        unset($ipsw[$category][$version]);
    }
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

  $cmds = array();
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
          $cmds[] = escapeshellarg('curl -s '.escapeshellarg($durl).' -o '.
            escapeshellarg($dest).'; echo '.escapeshellarg(
            'Done downloading '.escapeshellarg(basename($durl)))."\n");
          echo 'Added IPSW '.escapeshellarg(basename($durl)).' to download '.
            "queue.\n";
        }
      }
    }
  }

  // Run curl commands in parallel
  if (count($cmds) > 0) {
    echo "Downloading IPSW files in parallel (this may take a while) ...\n";
    passthru('echo '.implode($cmds).' | parallel --no-notice '.
      '-j '.escapeshellarg($parallel));
  }

  // Remove the synchronization directory lock
  unlink($path.'/sync.lock');

  echo "IPSW synchronization complete.\n";
