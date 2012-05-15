DJ Thread's MPC Tool
====================

This is my personal wrapper to mpc, the CLI client for [MPD](http://www.musicpd.org/). It's intended to make adding music to the playlist as easy as possible.

It operates under the idea that a function compiles a list of results (aka your list of "subject" items), the --choose (-c) flag, if enabled, lists the results and allows you to cherry-pick from them (otherwise all results are used), and finally the results will have some action executed on them:

    [ Function returns results (eg. latest music, random albums, etc) (you must specify this) ]
        -->  [ Choose menu pares them down (default: on) ]
        -->  [ Action operates on the final list. (default: Replace MPD Playlist and Play) ]

The method of gathering the list of subject items must be defined, but the above reflects the default, otherwise. The diagram reflects what would happen if you invoked "mpct.php --latest". The choose menu is used by default (use -g to GO and skip it), and the files will replace the MPD playlist.


Shell Aliases
-------------

For comfortable usage of mpct at the shell, I highly recommend using something like the aliases that I include. Simply use "mpct.php al" to see them, and if desired, add them to your bashrc file. (Use something like "mpct.php al >> ~/.bashrc")


Configuration File
------------------

If the default parameters (listed at the bottom of this file) don't quite suit you, you are able to override them. You'll find a sample config file in the original distribution called mpct.conf.php. Simply copy this file to ~/.mpct.conf.php and tweak to your taste.


Modes
-----

Modes are arrays of parameters (see next section) that override default settings. This allows you to modify a whole set of default settings and invoke your custom action with the "-o modename" flag. (You can, of course, use additional command line parameters as well.) For example, here's a mode setting I have in my ~/.mpct.conf.php that allows me to choose from the most recent 10 episodes of my podcast:

        'show'   => array(
            'func'       => 'latest',
			'latestRoot' => '/storage/music/Thread/show',
            'deep'       => 0,
            'num'        => 10,
        ),

Here's one that could add the songs to DeadBeef instead of MPD:

        'beef'   => array(
            'func'       => 'randomTracks',
            'num'        => 10,
            'action'     => 'exe',
            'exe'        => 'deadbeef X',
        ),


Parameters
----------

The parameters are a set of internal variables that dictate the state of the tool and are defined and overridden in a number of ways.

 1. The base defaults are listed at the bottom of this file.
 2. The parameters defined in the top level of the $p array in ~/.mpct.conf.php will override.
 2. Any modes that are selected (with -o mode1,mode2) corresponding with the keys of the $p['modes'] array in the config file will then override these defaults, in order.
 3. Finally, any command-line arguments and anything they imply will override.



Choose Menu
-----------

By default, you'll be given the list of results from the function to choose from. This will allow you to choose the specific subjects to act upon.

At the prompt, enter one or more parameters, separated by spaces, then press return.

 - "h" will give you help similar to this listing.
 - A simple number (eg. "3") will include the corresponding result as a subject.
 - A range (eg. "2-6") will include the whole range as a subject.
 - "A" will include ALL results as subjects.
 - "a" will enable append mode; simply an alternative to the command-line flag.
 - "m" give me MORE results after this input. (But still process other parameters.)
 - Simply press return with no input to exit.


By-Toplevel Random
------------------

In my collection, I like to split the organization essentially into sub-collections with each top-level directory named by genre. It's never perfect, especially as my favorite music spawns multiple genres, but it saves my eyes and file managers from having to process an entire directory loaded with folders. (Of course, searching is a great way to find something in particular.) If you don't divide your collection into directory-based "subcollections" in this way, you can safely ignore this section. If you find it interesting, however, to load random music by these directory-based locations, read on!

To set up this feature, be sure and define the $map array in your config file. There is an example array to get you started, but it's simply an array where the keys (on the left) are short codes and the right are directories relative to the MPD root. (The leading slash should is omitted.)

To activate this feature, simply use the --by-toplevel (-bt) flag in conjunction with a random function: --random-albums (-ra) or --random-tracks (-rt). You then have the option of either using a short code like "-ra -bt db" or leaving it off like "-ra -bt" in which case you'll be given the list of short codes to pick from.

When this flag is used, only music within that directory will be randomly selected.

Even when this flag is *not* used, if you have configured the $map array, the random selection features will only use music from these directories. I did this so I could leave some directories entirely out of my randomness functions. If you don't like this behavior, let me know and I'll think about making this more flexible. :)


Remote Use (Somewhat Tricky with --latest)
------------------------------------------

You can very easily use mpct to interact with a remote MPD instance since mostly everything is done through the mpc tool, however, the --latest feature will find recently-added music by the modification time in the filesystem. This means that it will not really work over the network so well. It is possible, though, to mount the music to the local machine, aim the latestRoot parameter at the mounted music, aim mpdRoot to the root of the music. These paths will most likely be different from the paths on the server end since they'll be prefixed with the mounted location, but since all the commands going to MPD are relative to MPD's root, it should work just fine.



Usage:

    DJ Thread's MPC Tool, v0.7

    Subjects (You need one of these):
     -rt, --random-tracks  Use random tracks 
     -ra, --random-albums  Use random albums
     -sa, --search-artist  Use tracks with artist names containing the parameter
     -sb, --search-album   Use tracks with album names containing the parameter
     -st, --search-title   Use tracks with titles containing the parameter
     -ta, --this-album     Use the currently playing album
     -la, --latest         Use latest albums

    Action Overrides (Default is to replace MPD playlist and hit play):
     -l,  --list           List only mode
     -s,  --simple         Simple list only mode
     -x,  --execute        Execute the argument for each result. X is replaced with
                           the absolute location. eg. -x 'cp -av X /mnt/hdd'
     -w,  --raw            The rest of the command line will go straight to mpc

    Modifiers:
     -bt, --by-toplevel    Ask which toplevel dir to use (a short code CAN follow)
     -n,  --num            Number of tracks to add (default depends on action)
     -c,  --choose         Select one or more from the results (default: on)
     -g,  --go             GO, use all results! (Disable choose mode)
     -a,  --append         Add tunage, preserving the current playlist
          --mpc            Full path to mpc executable
     -h,  --host           set the target host (default: localhost)
     -p,  --port           set the target port (default: 6600)
     -r,  --refresh        Refresh MPD's latestRoot first.
     -f,  --full-paths     Show system-absolute pathnames in output
     -q,  --quiet          Less output
     -o,  --mode           Specify the key(s) of the 'modes' array in the config
                           file to overwrite default params with. eg. -o mode1,mode2
     -al, --aliases        Nifty aliases. Recommended: mpct.php -al >> ~/.bashrc
     -?,  --help           this.
                    (The leading hyphen is optional for short flags.)


Web Interface!
--------------

Yeah, there is a jQuery Mobile web interface. Simply make the files in the web/ directory web accessible and configure a couple things atop index.php !

![jQuery Mobile Web Interface](https://github.com/djthread/mpct/blob/master/Web%20UI%20Screenshot.png?raw=true)


Appendix of Parameters
----------------------

        'func'       => null,   // Actual function to call. It will gather the target(s)
                                //   can be: latest, search, randomTracks, randomAlbums
                                //   or playThisAlbum.
                                //   Also valid but less useful are: raw, aliases
        'action'     => 'mpc',  // 'mpc' to add to mpd, 'exe' to execute cmds, or 'list'
                                //   to simply list the hits

        'host'       => null,   // override the host default/environment
        'port'       => null,   // override the host default/environment
        'mpc'        => null,   // full path/file to the mpc binary
        'refresh'    => false,  // refresh MPD's latestRoot
        'num'        => null,   // num of results. defaults depending on action.
        'append'     => false,  // just add to the end of the playlist
        'choose'     => true,   // choose from the results !
        'bt'         => null,   // short code for "by toplevel"
        'simpleOut'  => false,  // Simple listing, used if action is list
        'exe'        => null,   // execute cmd for each hit. X is replaced with result.
        'exclude'    => array(),  // array of dirs to skip over
        'modes'      => array(),  // label => array of key/vals to override parameters
        'mode'       => null,   // selected label of modes array, if any
        'fullPaths'  => false,  // show system-absolute pathnames in output
        'colors'     => true,   // use colors! yayy!! \o/
        'debug'      => false,  // show tons of unorganized output
        'quiet'      => false,  // show less output... different output
        'extensions' => 'flac,mp3,ogg',  // extensions to look for on music files

        'rawCmd'     => null,   // overrides everything, execute args for mpc

        // latest-mode params
        'latestRoot' => '/storage/music/tmp/stage2',  // root dir for latest music
        'mpdRoot'    => '/storage/music',             // root dir of mpd
        'deep'       => 1,                            // how many dirs deep to look

        // override if you must...
        'defNumTrks' => 10,    // default num for --random-tracks
        'defNumAlbs' => 10,    // default num for --random-albums
        'defNumLa'   => 10,    // default num for --latest
        'alfredMode' => false, // do certain things for an alfred plugin
        'btRandom'   => null,  // "latest": using toplevel picking? false = all music

        // internal only
        'mpcCmd'     => null,  // full mpc cmd prefix: binary, host, port
        'extRegex'   => null,  // regex to recognize music file extensions
