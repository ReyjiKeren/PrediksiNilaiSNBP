const items = document.querySelectorAll(".ptn-item");

items.forEach(item => {
    item.addEventListener("click", () => {

        items.forEach(i => {
            i.classList.remove(
                "bg-blue-50",
                "border-l-4",
                "border-blue-700"
            );
        });

        item.classList.add(
            "bg-blue-50",
            "border-l-4",
            "border-blue-700"
        );
    });
});