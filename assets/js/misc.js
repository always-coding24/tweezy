





function wrapSlangsWithTooltips(elementId,) {
    let slangs = [
        { word: "ASAP", tooltip: "As Soon As Possible" },
        { word: "FYI", tooltip: "For Your Information" },
        { word: "EOD", tooltip: "End Of Day" },
        { word: "TTYL", tooltip: "Talk To You Later" }
    ];
    for (let i = 0; i < $(elementId).length; i++) {
        let element = $(elementId)[i];



        let text = element.innerHTML;

        slangs.forEach(item => {
            let wordRegex = new RegExp(`\\b(${item.word})\\b`, 'gi');
            text = text.replace(wordRegex, function (matched) {
                return `<span class="slang-term" data-toggle="tooltip" title="${item.tooltip}">${matched}</span>`;
            });
        });

        element.innerHTML = text;
    }
    $('[data-toggle="tooltip"]').tooltip();

function copy(id) {
    let copy = `#message-${id} .message-text  p`
    var copyText = document.querySelector(copy).innerText;
    if (!copyText) {
        return;
    }
    navigator.clipboard.writeText(copyText);


    Swal.fire({
        title: "Copied ",

        icon: "info",
        toast: true,
        position: "bottom-end",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,

    });

}  // Ensure jQuery is loaded
$(document).ready(function(){
wrapSlangsWithTooltips(".message-text >p ");

})
// Function to wrap slang terms with Bootstrap tooltips
}