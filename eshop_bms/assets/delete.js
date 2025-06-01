function delete_(id) {
    const response = fetch("/bms/product_delete/" + id, {
        method: "DELETE"
    });
    const row = document.getElementById(id);
    row.remove();
    ids.splice(ids.indexOf(id + ""), 1);
    
    showPage();
}
