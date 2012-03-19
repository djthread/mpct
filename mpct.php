#!/usr/bin/php
<?php
/**
 * The only configuration in this script is this array.
 *
 * Top-level directory map (short code => full path)
 *
 * The keys will be the short codes usable with the --by-toplevel/-bt flag. 
 *
 * ALSO: When you do regular random (not by toplevel) these dirs will also 
 * be used for randomness. I've left a few that I am not generally 
 * interested in out of this list. If you want to start at the root and 
 * have random music selected from the entire collection, simply empty this
 * array.
 */

require 'MPCWorker.php';
MPCWorker::runFromCLIArguments($argv);
