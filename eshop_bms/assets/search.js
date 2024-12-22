var searchInputElement = document.getElementById("searchInput");

async function search_() {
    //TODO: rewrite function
    console.log("clicked search")
    var response = await fetch('/search',{
        method: "POST",
        headers: {
            'content-type' : 'application/json'
        },
        body: JSON.stringify({
            "query" : searchInputElement.value
        })
    });
    //console.log(await response.text());
    
    var jsonResponse = await response.json();
    var results = jsonResponse["results"];
    
    // Debugging: printing detailed results
    console.log("Results: ", results);
    results.forEach((result, index) => {
        console.log(`Result ${index + 1}:`, result);
    });
    window.location.href = 'results/';
}