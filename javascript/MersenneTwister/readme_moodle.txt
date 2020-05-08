The Mersenne Twister library is available from
https://gist.github.com/banksean/300494. The version is not known and at the
time of this writing the library is 10 years old. It is already included with
the block_behaviour plugin and nothing more needs to be done.

To upgrade the library (which may or may not break existing code), download the
latest release and wrap the code as an AMD module. See the currently wrapped
library in amd/src/mersenne-twister.js for an example. The original file should
be store in this directory, while the AMD wrapped file resides in amd/src. Grunt
will need to be run to minify the new library. The lib/thirdpartylibs.xml file
can then be updated.
