<script type="text/javascript">
/* http://www.kryogenix.org/code/browser/searchhi/ */
function highlightWord(node,word) {
    // Iterate into this nodes childNodes
    if (node.hasChildNodes) {
        var hi_cn;
        for (hi_cn=0;hi_cn<node.childNodes.length;hi_cn++) {
            highlightWord(node.childNodes[hi_cn],word);
        }
    }

    // And do this node itself
    if (node.nodeType == 3) { // text node
        tempNodeVal = node.nodeValue.toLowerCase();
        tempWordVal = word.toLowerCase();
        if (tempNodeVal.indexOf(tempWordVal) != -1) {
            pn = node.parentNode;
            if (pn.className != "searchword") {
                // word has not already been highlighted!
                nv = node.nodeValue;
                ni = tempNodeVal.indexOf(tempWordVal);
                // Create a load of replacement nodes
                before = document.createTextNode(nv.substr(0,ni));
                docWordVal = nv.substr(ni,word.length);
                after = document.createTextNode(nv.substr(ni+word.length));
                hiwordtext = document.createTextNode(docWordVal);
                hiword = document.createElement("span");
                hiword.className = "searchword";
                hiword.appendChild(hiwordtext);
                pn.insertBefore(before,node);
                pn.insertBefore(hiword,node);
                pn.insertBefore(after,node);
                pn.removeChild(node);
            }
        }
    }
}

function SearchHighlight() {
    if (!document.createElement) return;
    words = "<%queryString%>".split(/\s+/);
    for (w=0;w<words.length;w++) {
        highlightWord(document.getElementsByTagName("body")[0],words[w]);
    }
}

window.onload = SearchHighlight;
</script>
<style>
.searchword {background-color: yellow;}
</style>
