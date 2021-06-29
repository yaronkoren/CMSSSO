function deleteCookie(c_name)
{
    document.cookie = encodeURIComponent(c_name) + "=deleted; expires=" + new Date(0).toUTCString();
}


function getParameterByName( name,href )
{
    name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
    var regexS = "[\\?&]"+name+"=([^&#]*)";
    var regex = new RegExp( regexS );
    var results = regex.exec( href );
    if( results == null )
        return "";
    else
        return decodeURIComponent(results[1].replace(/\+/g, " "));
}

function handleMessage(siteCookie, message)
{
    var config = mw.config.get('crossDomainSSOVars');
    //alert(config.wgSSOConsumerTopLevelDomain);
    if(message != "")
    {
        if(typeof(siteCookie) != "undefined" && siteCookie != null && siteCookie != "")
        {
            if(siteCookie != message)
            {
                //alert("Cookie data are not same");
                //Delete the sub-domain and domain cookie first as a cleanup process.
                $.cookie("token", null);
                $.cookie("token", null, { expires: -1, path: "/", domain: config.wgCrossSiteSSOConsumer, secure: false });
                $.cookie("token", null, { expires: -1, path: "/", domain: config.wgSSOConsumerTopLevelDomain, secure: false });

                //Setup the cookie now
                $.cookie("token", message, { path: "/", domain: "<?php echo($wgCrossSiteSSOConsumer);?>", secure: false });
                document.cookie="token="+message;
                var url = window.location.href;
                url = UpdateQueryString('rel', null , url);
                window.location.href = url;
                //window.location.reload();
            }
            else
            {
                //alert("I have site data");
                //alert(siteCookie);
                /*
                 * Since we are using Hook for the SingleSignOn extension, this is needed as that extension
                 * executes just once instead of executing multiple times (in case there are no hooks used)
                 */
                if($("#loginToolBarItem").text() == "Log in")
                {
                    //alert("Login Still present");
                    //alert(siteCookie);
                    reloadPageOnceMore(2);
                }
                else
                    return;
            }
        }
        else
        {
            //alert("setting up cookie");
            //Delete the sub-domain and domain cookie first as a cleanup process.
            $.cookie("token", null, { expires: -1, path: "/", domain: config.wgCrossSiteSSOConsumer, secure: false });
            $.cookie("token", null, { expires: -1, path: "/", domain: config.wgSSOConsumerTopLevelDomain, secure: false });

            //Setup the cookie now..
            $.cookie("token", message, {path: "/", domain: config.wgCrossSiteSSOConsumer, secure: false });
            document.cookie="token="+message;
            var url = window.location.href;
            url = UpdateQueryString('rel', null , url);
            window.location.href = url;
            //window.location.reload();
        }
    }
}

function updateQueryStringParameter(uri, key, value) {
    var re = new RegExp("([?|&])" + key + "=.*?(&|$)", "i");
    separator = uri.indexOf('?') !== -1 ? "&" : "?";
    if (uri.match(re)) {
        return uri.replace(re, '$1' + key + "=" + value + '$2');
    }
    else {
        return uri + separator + key + "=" + value;
    }
}


function UpdateQueryString(key, value, url) {
    if (!url) url = window.location.href;
    var re = new RegExp("([?|&])" + key + "=.*?(&|#|$)(.*)", "gi");

    if (re.test(url)) {
        if (typeof value !== 'undefined' && value !== null)
            return url.replace(re, '$1' + key + "=" + value + '$2$3');
        else {
            return url.replace(re, '$1$3').replace(/(&|\?)$/, '');
        }
    }
    else {
        if (typeof value !== 'undefined' && value !== null) {
            var separator = url.indexOf('?') !== -1 ? '&' : '?',
                hash = url.split('#');
            url = hash[0] + separator + key + '=' + value;
            if (hash[1]) url += '#' + hash[1];
            return url;
        }
        else
            return url;
    }
}


function reloadPageOnceMore(noOfTimes)
{
    var url = window.location.href;
    var relValue = parseInt(getParameterByName("rel", url));
    //alert(relValue);
    if(typeof(relValue) == "undefined" || relValue == "" || relValue == null || isNaN(relValue))
    {
        if (url.indexOf("?") > -1){
            url += "&rel=1";
        }else{
            url += "?rel=1";
        }
        //window.location.reload();
        window.location.href = url;
    }
    else if(relValue < noOfTimes)
    {
        url = UpdateQueryString('rel', relValue+1 , url);
        window.location.href = url;
    }
    else
        return;
}

function delayedJsonPCall()
{
    var siteCookie = $.cookie("token");
    //alert(siteCookie);

    var config = mw.config.get('crossDomainSSOVars');
    var wgCrossSiteSSOProvider = config.wgCrossSiteSSOProvider;
    //alert(wgCrossSiteSSOProvider);
    var url = "https://" + wgCrossSiteSSOProvider + "/api.php?action=pwuser&subaction=gettoken&format=json";
    //alert(url);

    $.ajax({
        url: url,
        dataType: "jsonp",
        crossdomain:true,
        async: true,
        cache:false,
        jsonpCallback: "jsonCallback",
        contentType: "application/json; charset = utf-8",
        success: function (data) {
            if(data.pwuser != null && data.pwuser != "")
            {
                handleMessage(siteCookie, data.pwuser);
            }
            else
            {
                if(typeof(siteCookie) != "undefined"  && siteCookie != "" && siteCookie != null)
                {
                    //alert(siteCookie);
                    //For some reason, delete cookie did not work.. Setting up in past
                    $.cookie("token", null, { expires: -1, path: "/", domain: config.wgCrossSiteSSOConsumer, secure: false });
                    $.cookie("token", null, { expires: -1, path: "/", domain: config.wgSSOConsumerTopLevelDomain, secure: false });
                    $.cookie("token", null);
                    deleteCookie("token");
                    //document.cookie = "token=;expires=Thu, 01 Jan 1970 00:00:00 GMT; domain='.$wgSSOConsumerTopLevelDomain.'";
                    var url = window.location.href;
                    url = UpdateQueryString('rel', null , url);
                    window.location.href = url;
                }
                else
                {
                    if($("#loginToolBarItem").text() == "Log out")
                    {
                        //alert("Logout Present");
                        //alert(siteCookie);
                        reloadPageOnceMore(2);
                    }
                    else
                        return;
                }
            }                        //alert(data.token);
        },
        error: function (request, status, error) {
            //alert(error);
        }
    });

}

//To Enable EasyXDM, restore the declaration of var socket from the backup



//easyXDM didn't work? Let us try it one more time using the JsonP
//To Enable EasyXDM, add delay for the jsonP call window.setTimeout( delayedJsonPCall, 500 ); // 0.5 seconds
$(document).ready(function(){

    var config = mw.config.get('crossDomainSSOVars');
    //alert(config.wgCrossSiteSSOProvider);


    var TokenMessage = "";
    var REMOTE = (function(){
        var remote = location.href;
        return remote.substring(0, remote.lastIndexOf("/"));
    }());
    window.setTimeout( delayedJsonPCall );
    var url = window.location.href;
    var relValue = getParameterByName( 'rel',url );
    var siteCookie = $.cookie("token");
    if(typeof(relValue) != "undefined" && relValue != "" && relValue != null && !isNaN(relValue) && relValue == 50 && (typeof(siteCookie) == "undefined" || siteCookie == "" || siteCookie == null))
    {
        url = UpdateQueryString('rel', null , url);
        window.location.href = url;
    }
});


