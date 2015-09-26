<?php
  // The base path at which IPSW files will be synchronized
  $path = '/var/ipsw';

  // The count of most recent versions per category (at least 1)
  $last_versions = 2;

  // The number of downloads to run concurrently (from 1 to 6)
  // NOTE: Apple limits maximum concurrent downloads to 6, which is why that
  //       limit is in place for this configuration variable
  $parallel = 6;
