The concave hull library is available from
https://github.com/emeeks/d3.geom.concaveHull. The version used in the
block_behaviour plugin is not known and at the time of this writing the library
had not been modified in 5 years. The library is already included with this
plugin and nothing more needs to be done.

To upgrade to the latest version, (which may or may not break existing code),
download the code from https://github.com/emeeks/d3.geom.concaveHull and place
it in this directory, overwriting the current version. The code from
d3.geom.concaveHull.js must be cut and pasted into the amd/src/modules.js file,
replacing the current concave hull code. Grunt must then be run to minify the
altered modules.js script. Finally, update the lib/thirdpartylibs.xml file with
the latest version number, if applicable.
