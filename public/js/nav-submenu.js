// মোবাইলে মেনুর সাব-বিভাগ (সারাদেশ ইত্যাদি) বন্ধ থাকে; ক্লিকে খোলে।
// ডেস্কটপে হোভারেই খোলে, তাই এই স্ক্রিপ্ট মোবাইল প্রস্থেই শুধু কাজ করে।
(function () {
    var mobile = window.matchMedia('(max-width: 991.98px)');

    function closeAll(except) {
        var open = document.querySelectorAll('.navbar-custom .nav-item.dropdown.is-open');

        for (var i = 0; i < open.length; i++) {
            if (open[i] !== except) {
                open[i].classList.remove('is-open');
            }
        }
    }

    document.addEventListener('click', function (event) {
        if (!mobile.matches) {
            return;
        }

        var link = event.target.closest('.navbar-custom .nav-item.dropdown > .nav-link');

        // "অন্যান্য" বুটস্ট্র্যাপের নিজের ড্রপডাউন, সেটি এই স্ক্রিপ্ট ছাড়াই খোলে
        if (!link || link.hasAttribute('data-bs-toggle')) {
            return;
        }

        var item = link.parentNode;

        if (item.classList.contains('is-open')) {
            item.classList.remove('is-open');

            return;
        }

        event.preventDefault();
        closeAll(item);
        item.classList.add('is-open');
    });
})();
