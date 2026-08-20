document.addEventListener('DOMContentLoaded', function () {
	var categoryLinks = document.querySelectorAll('.category_item');
	var productItems = document.querySelectorAll('.product-item');

	if (!categoryLinks.length || !productItems.length) return;

	categoryLinks.forEach(function (link) {
		link.addEventListener('click', function (e) {
			e.preventDefault();
			var category = link.getAttribute('category');

			categoryLinks.forEach(function (l) { l.classList.remove('active'); });
			link.classList.add('active');

			productItems.forEach(function (item) {
				var show = category === 'all' || item.getAttribute('category') === category;
				item.style.display = show ? '' : 'none';
			});
		});
	});
});
