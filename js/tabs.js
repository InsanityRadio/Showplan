document.addEventListener("DOMContentLoaded", function() {

	function hide (container) { container.style.display = 'none'; }
	function show (container) { container.style.display = 'block'; }

	function tabs (container) {

		var menu = container.querySelectorAll(".showplan-schedule-tab");
		var tabs = container.querySelectorAll(".showplan-tab");

		[].forEach.call(tabs, function (tab) {
			if (tab.classList.contains("today")) {
				return;
			}
			hide(tab);
		});

		[].forEach.call(menu, function(item) {

			if (item.classList.contains("today")) {
				item.classList.add("current");
			}

			item.addEventListener("click", function () {
				console.log("clicked!", this);
				[].forEach.call(tabs, hide);
				show(container.querySelector(item.getAttribute("for")));
				container.querySelector(".showplan-schedule-tab.current").classList.remove("current");
				this.classList.add("current");
			})

		});


	}

	var containers = document.querySelectorAll(".showplan-schedule-container");
	[].forEach.call(containers, tabs);

});