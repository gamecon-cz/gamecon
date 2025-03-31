if (!sessionStorage.getItem("aprilFools")) {
    document.getElementById("aprilFools").style.display = "flex";
    sessionStorage.setItem("aprilFools", "true");
}

function closeModal() {
    document.getElementById("aprilModal").style.display = "none";
}