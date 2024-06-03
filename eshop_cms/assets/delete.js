function delete_(id) {
    var response = fetch("product_delete/" + id, {
        method: "DELETE"
    });
    var row = document.getElementById(id);
    row.remove();
    ids.splice(ids.indexOf(id+""), 1);

    showPage();
}
