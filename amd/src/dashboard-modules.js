define(['block_behaviour/d3', 'block_behaviour/dashboard'],
       function(d3, dash) {
            return {
                init: function() {
                    // Pass the packages to the plugin's client side.
                    window.dataDrivenDocs = d3;
                    window.behaviourAnalyticsDashboard = dash;
                }
            };
        });
