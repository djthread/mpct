DJ Thread's MPC Tool
--------------------

This is my personal wrapper to mpc, the CLI client for [MPD](http://www.musicpd.org/). It's intended to make adding random music to the playlist as easy as possible.

Be sure and check the top of the script. You'll want to change the $toplevelMap array to work with your library. If you like the idea of using the -bt flag to get a random artist/album from within a certain directory, define them here with the shortcodes you'd like to use to refer to them. (Note that full-collection randomness will still only use the dirs defined here.)

If you prefer all your randomness to be across the full collection, simply empty this array. (just leave "array()")

Usage:

    DJ Thread's MPC Tool, v0.6

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
     -b,  --deadbeef       Add to deadbeef
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

Yeah, there is a jQuery Mobile web interface for this. Simply make the files in the web/ directory web accessible and configure a couple things atop index.php !

![jQuery Mobile Web Interface](https://github.com/djthread/mpct/blob/master/Web%20UI%20Screenshot.png?raw=true)
