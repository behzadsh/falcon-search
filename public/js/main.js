(function (document, window) {

    var config = {
        baseUrl: "search"
    };

    // --------------------
    //      url handlers
    // --------------------
    function QueryString() {
        // This function is anonymous, is executed immediately and
        // the return value is assigned to QueryString!
        var query_string = {};
        var query = window.location.search.substring(1);
        var vars = query.split("&");
        for (var i = 0; i < vars.length; i++) {
            var pair = vars[i].split("=");
            // If first entry with this name
            if (typeof query_string[pair[0]] === "undefined") {
                query_string[pair[0]] = decodeURIComponent(pair[1]);
                // If second entry with this name
            } else if (typeof query_string[pair[0]] === "string") {
                var arr = [query_string[pair[0]], decodeURIComponent(pair[1])];
                query_string[pair[0]] = arr;
                // If third or later entry with this name
            } else {
                query_string[pair[0]].push(decodeURIComponent(pair[1]));
            }
        }
        return query_string;
    }

    function updateBrowserUrlByQueries(key, value, url) {
        window.history.pushState("", document.title, UpdateQueryString(key, value, url));
    }

    function UpdateQueryString(key, value, url) {
        if (!url) url = window.location.href;
        var re = new RegExp("([?&])" + key + "=.*?(&|#|$)(.*)", "gi"),
            hash;

        if (re.test(url)) {
            if (typeof value !== 'undefined' && value !== null)
                return url.replace(re, '$1' + key + "=" + value + '$2$3');
            else {
                hash = url.split('#');
                url = hash[0].replace(re, '$1$3').replace(/(&|\?)$/, '');
                if (typeof hash[1] !== 'undefined' && hash[1] !== null)
                    url += '#' + hash[1];
                return url;
            }
        }
        else {
            if (typeof value !== 'undefined' && value !== null) {
                var separator = url.indexOf('?') !== -1 ? '&' : '?';
                hash = url.split('#');
                url = hash[0] + separator + key + '=' + urlEncodeString(value);
                if (typeof hash[1] !== 'undefined' && hash[1] !== null)
                    url += '#' + hash[1];
                return url;
            }
            else
                return url;
        }
    }

    function removePagination(paginationContainer) {
        paginationContainer.parentNode.removeChild(paginationContainer);
    }

    function urlEncodeString(value) {
        if (typeof value == 'string') value = value.replace(/%26/g, "&").replace(/%2B/g, "+")
        value = encodeURIComponent(value);
        return value.replace(/%20/g, "+");
    }

    function decodeQueryString(queryString) {
        return decodeURIComponent(queryString.replace(/\+/g, "%20"));
    }

    // ------------------
    //  global variables
    // ------------------
    var searchedString = "";
    var previousXHRRequest = null;

    // ------------------------------------
    //   render tools (return html string)
    // ------------------------------------
    function resultBoxHtmlByResultBoxObj(resultBoxObj) {
        Object.keys(resultBoxObj).forEach(function (key) {
            if (typeof(resultBoxObj[key]) == 'object') {
                resultBoxObj[key] = resultBoxObj[key].join(' ..... ')
                if (key != 'title') resultBoxObj[key] += ' .....'
            } else if (resultBoxObj[key] != undefined) {
                resultBoxObj[key] = resultBoxObj[key].replace("'", "\'");
            }
        });
        return '<div class="resultItemBox">'
            + '<a href="' + resultBoxObj.url + '">'
            + '<h2>' + resultBoxObj.title + '</h2>'
            + '</a>'
            + ((resultBoxObj.url) ? ('<cite>' + resultBoxObj.url + '</cite>') : '')
            + '<p>'
            + ((resultBoxObj.date) ? ('<span>' + resultBoxObj.date + ' – </span>') : '')
            + resultBoxObj.desc + '</p>'
            + '</div>';
    }

    function returnHtmlForPaginationElementByNumber(num) {
        var url = UpdateQueryString("page", num);
        var active = false;
        if (QueryString().page) {
            if (num == QueryString().page) {
                active = true;
            }
        } else if (num == 1) {
            active = true;
        }

        return '<li><a class="' + (active ? "active" : "") + '" onclick="paginate(event, ' + num + ')" href="' + url + '">' + num + '</a></li>';
    }

    function renderResponse(response, params) {
        if (typeof response != "object") {
            console.error("type of response is not valid, the response must be array of objects");
            return false;
        }

        var paginationContainer = document.getElementsByClassName("pagination")[0];
        var resultHtml = "";

        if (response.hits.hits.length > 0) {
            response.hits.hits.forEach(function (item) {
                var resultItem = {
                    title: (item.highlight.title) ? item.highlight.title : item._source.title,
                    desc: item.highlight.content,
                    url: item._source.original.url,
                    date: item._source.date
                };
                resultHtml += resultBoxHtmlByResultBoxObj(resultItem);
            });

            var resultInfoContainer = document.getElementsByClassName("resultInfo")[0];
            if (resultInfoContainer) {
                resultInfoContainer.innerHTML = '<span>' + response.hits.total
                    + ' results in ' + response.took
                    + ' miliseconds' + '</span>';
            }

            var lastPage = Math.ceil(response.hits.total / 10);
            var paginationHtml = "";
            if (lastPage > 1) {
                for (var i = 0; i < lastPage; i++) {
                    paginationHtml += returnHtmlForPaginationElementByNumber(i + 1);
                }

                if (paginationContainer) {
                    paginationContainer.innerHTML = paginationHtml;
                }
            } else {
                removePagination(paginationContainer);
            }
        } else {
            resultHtml = '<p>Your search - <b>' + params.q + '</b> - did not match any documents.</p>';
            removePagination(paginationContainer);
        }

        var resultContainer = document.getElementsByClassName("resultList")[0];
        if (resultContainer) {
            resultContainer.innerHTML = resultHtml;
        }

    }

    // --------------------------------------------
    //      prepare view for render result list
    // --------------------------------------------
    function prepareToRenderResult() {

        var miniLogo = document.getElementsByClassName('logoMini')[0];
        miniLogo.className = "logoMini"

        var largeLogo = document.getElementsByClassName('logo')[0];
        if (largeLogo.className.indexOf("togglePosition") == -1) {
            largeLogo.className += " hidden";
        }

        var searchBoxContainer = document.getElementsByClassName('searchBoxContainer')[0];
        if (searchBoxContainer) {
            if (searchBoxContainer.className.indexOf("searchBoxContainer") != -1) {
                searchBoxContainer.className = "searchBoxContainerWithResults";
            }
        }

        var searchBox = document.getElementsByClassName("searchBox")[0];
        if (searchBox.className.indexOf("togglePosition") == -1) {
            searchBox.className += " togglePosition";
        }

        if (document.getElementsByClassName("resultInfo").length == 0) {
            var resultInfoContainer = document.createElement("div");
            resultInfoContainer.className = "resultInfo";
            document.getElementsByClassName("mainContainer")[0].appendChild(resultInfoContainer);
        }

        if (document.getElementsByClassName("resultList").length == 0) {
            var resultListContainer = document.createElement("div");
            resultListContainer.className = "resultList";
            document.getElementsByClassName("mainContainer")[0].appendChild(resultListContainer);
        }

        if (document.getElementsByClassName("pagination").length == 0) {
            var resultListContainer = document.createElement("ul");
            resultListContainer.className = "pagination";
            document.getElementsByClassName("mainContainer")[0].appendChild(resultListContainer);
        }
    }

    // ------------------------
    //      form submission
    // ------------------------
    function submitForm(e) {
        if (!e) e = window.event;
        e.preventDefault();
        var searchQuery = document.getElementById("searchQuery").value;

        if (searchQuery.length == 0) return false;

        searchedString = searchQuery = searchQuery.replace("&", "%26").replace("+", "%2B");

        if (previousXHRRequest) previousXHRRequest.abort(); // cancel previous XHR request in progress
        sendRequestByParams({
            q: searchQuery
        })
    }

    function paginate(e, num) {
        if (!e) e = window.event;
        e.preventDefault();
        var requestObj = QueryString();
        requestObj['page'] = num;
        sendRequestByParams(requestObj);
    }

    // ----------------------------------
    //      send XHR request by params
    // ----------------------------------
    function sendRequestByParams(params) {
        // reset url
        updateBrowserUrlByQueries(null, null, window.location.href.replace(window.location.search, ""));

        var urlParamString = "";
        Object.keys(params).forEach(function (keyOfParams) {
            urlParamString += "&" + keyOfParams + "=" + params[keyOfParams];
            updateBrowserUrlByQueries(keyOfParams, params[keyOfParams]);
        });

        urlParamString = urlParamString.replace("&", "");

        var http = new XMLHttpRequest();
        var url = config.baseUrl;
        http.open("POST", url, true);
        previousXHRRequest = http;

        //Send the proper header information along with the request
        http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        http.onreadystatechange = function () {
            //Call a function when the state changes.
            if (http.readyState == 4 && http.status == 200) {
                prepareToRenderResult();
                var response = JSON.parse(http.responseText);
                renderResponse(response, params);
            }
        };
        http.send(urlParamString);
    }

    // ---------------------------------------------------
    //   check the url params in initialization of page
    // ---------------------------------------------------
    if (Object.keys(QueryString()).length > 0 && Object.keys(QueryString())[0].length > 0) {
        if (QueryString().q) document.getElementById("searchQuery").value = decodeQueryString(QueryString().q);
        sendRequestByParams(QueryString());
    }

    // exports
    window.submitForm = submitForm;
    window.paginate = paginate;
    window.QueryString = QueryString;
})(document, window);