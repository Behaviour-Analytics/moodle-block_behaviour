The No UI Slider library is available from
https://github.com/leongersen/noUiSlider. The version included with
block_behaviour is 14.3.0, although the latest release at the time of this
writing is 14.4.0. The library is already included with the plugin and nothing
more needs to be done.

To upgrade the library, download the latest release from
https://github.com/leongersen/noUiSlider and place it in this directory. Then,
copy the distribute/nouislider.js file into the amd/src folder and the
distribute/nouislider.min.js file into the amd/build folder (or run grunt). If
the name of the minified CSS has changed, it will need to be updated in the
view.php script. Finally, update the lib/thirdpartylibs.xml file.
