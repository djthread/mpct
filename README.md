DJ Thread's MPC Tool

This is my personal wrapper to mpc, the CLI client for MPD [http://www.musicpd.org/]. It's intended to make adding random music to the playlist as easy as possible.

Be sure and check the top of the script. You'll want to change the $toplevelMap array to work with your library. If you like the idea of using the -bt flag to get a random artist/album from within a certain directory, define them here with the shortcodes you'd like to use to refer to them. (Note that full-collection randomness will still only use the dirs defined here.)

If you prefer all your randomness to be across the full collection, simply empty this array. (just leave "array()")

Usage:

   -h,  --host           set the target host (default: localhost)
   -p,  --port           set the target port (default: 6600)
   -rt, --random-tracks  add random tracks to the playlist
   -10, --ten            play/add 10 random tunes
   -bt, --by-toplevel    ask which toplevel dir to use (a short code CAN follow)
   -ra, --random-album   play/add random album
   -c,  --count          how many tracks to add (default: 10)
   -ta, --this-album     play/add the album from which the current song is
   -a,  --append         add tunage, peserving the current playlist
        --mpc            full path to mpc executable
   -d,  --debug          echo debugging information
   -q,  --quiet          sssshhh
   -?,  --help           this.


Web Interface!

Yeah, there is a jQuery Mobile web interface for this. Simply make the files in the web/ directory web accessible and configure a couple things atop index.php !

![jQuery Mobile Web Interface](https://github.com/djthread/mpct/blob/master/Web%20UI%20Screenshot.png?raw=true)
