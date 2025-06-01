const searchInputElement = document.getElementById("searchInput");

async function search_() {
    const spinner = document.getElementById("loadingSpinner");
    const locale = document.getElementById("current-locale").value;

    try {
        // 显示 loading 动画
        spinner.style.display = "block";

        const response = await fetch('/bms/search', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                query: searchInputElement.value
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const jsonResponse = await response.json();

        // 可以根据返回结果进行处理或存储结果（如果有 sessionStorage 用于传值也可以）
        const results = jsonResponse["results"];

        // 跳转到结果页面
        window.location.href = `/bms/results?_locale=${locale}`;
    } catch (error) {
        console.error("Search failed:", error);
        alert("Search failed. Please try again.");
    } finally {
        // 隐藏 loading 动画
        spinner.style.display = "none";
    }
}