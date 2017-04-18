document.addEventListener("DOMContentLoaded", function() {

	if (!window.showplanStyle ) {
		window.showplanStyle = 'default';
	}

	if (!showplanTable) {
		return;
	}

	function update () {

		var times = [];
		var hasSelectedReal = false;

		selected.forEach(function (a) {
			if (a.classList.contains("occupied")) {
				hasSelectedReal = true;
			}
			if (showplanStyle == 'dates') {
				var t = a.getAttribute('date');
				times.push(t + 'U');
			} else {
				times.push(a.getAttribute('id').split('-')[2]);
			}
		});

		console.log(times.join(';'));

		document.getElementById('showplan-tools-times').value = 
		document.getElementById('showplan-tools-times2').value = times.join(";");

		document.getElementById('showplan-tools-remove-ids').value = selected.map(function (a) { return a.dataset.id }).join(";");

		document.getElementById('showplan-tools-assign').style.display = selected.length && !hasSelectedReal ? 'block' : 'none';
		document.getElementById('showplan-tools-remove').style.display = selected.length && hasSelectedReal ? 'block' : 'none';

	}

	function draw (showData, ghost) {

		for(var i in showData) {

			var show = showData[i];

			if (showplanStyle == 'dates' && !ghost) {
				drawDates(show);
			} else {
				drawRegular(show, ghost);
			}

		}

	}

	function drawDates (show) {
		var start = parseInt(show.start_time);
		var length = parseInt(show.end_time) - start;
		for (var j = 0; j < length / 3600; j++) {
			var element = document.querySelector("#showplan-schedule-table td[date='" + (start + j * 3600) + "']");
			if (element != null)
				drawShow(element, show, j == 0, (j * 3600) != show.length - 3600, false);
		}
	}

	function drawRegular (show, ghost) {
		var day = show.day;
		var hour = show.hour - 1;
		for (var j = 0; j < show.length / 60; j++) {

			if (parseInt(hour) + 1 > 23) {
				day++;
				hour = 0;
			} else 
				hour++;

			console.log(hour);
			var element = document.getElementById("showplan-schedule-" + day + "T" + hour + "Z");
			drawShow(element, show, j == 0, (60 + j * 60) != show.length, ghost);

		}
	}

	function drawShow (element, show, first, notFinal, ghost) {
		if (ghost) {
			element.className = "ghost";
		} else {
			element.className = "occupied";
		}
		element.innerHTML = "<h1></h1><span></span>";
		element.dataset.id = show.id;
		if (first) {
			element.querySelector("h1").textContent = show.show.name;
			element.querySelector("span").textContent = show.show.hosts;
		}
		if (notFinal) {
			element.classList.add("continuing");
		} else {
			element.classList.remove("continuing");
		}
	}

	var table = document.getElementById("showplan-schedule-table");
	var qs = [].slice.apply(table.querySelectorAll("td:not(:first-child)"));

	var selected = [];
	var lastSelected = null;

	qs.forEach(function (td) {

		td.addEventListener("click", function (event) {

			event.preventDefault();
			event.stopPropagation();
			var _this = this;

			var targets = [];
			if (event.shiftKey) {
				// We need to sort everything by its reverse order.
				var qs2 = new Array(qs.length);

				qs.forEach(function (a) {
					var cellIndex = a.cellIndex;
					var rowIndex = a.parentNode.rowIndex;
					qs2[rowIndex + 24 * cellIndex] = a;
				});

				var i = qs2.indexOf(lastSelected);
				var j = qs2.indexOf(this);
				// Compensate for a backwards selection
				if (i > j) {
					var k = j;
					j = i;
					i = k;
				}
				for (i++; i <= j; i++) {
					if(qs2[i] != null)
						targets.push(qs2[i]);
				}
			} else
				targets = [this];

			targets.forEach(function (target){ 
				if (selected.indexOf(target) !== -1) {
					selected = selected.filter(function (a) { return a != target; });
					target.classList.remove("selected");
					return;
				}
				selected.push(target);
				target.classList.add("selected");
			});
			update();
			lastSelected = this;
		});

		td.addEventListener("dblclick", function (event) {
			qs.forEach(function(a) { 
				a.classList.remove("selected");
			})
			selected = [];
			lastSelected = this;
			update();
		})

	});

	// Reset/clear all the existing hidden data
	update();

	window.addEventListener("keypress", function (event) {
		if(event.keyCode != 13 || selected.length == 0) return;
	})


	if (window.ghostData) {
		draw(ghostData, true);
	}
	draw(showData, false);


	if (document.getElementById("showplan-publish-countdown")) {
		var e = document.getElementById("showplan-publish-countdown");
		var d = Date.now();

		function setDate() {
			var date = Math.floor(Date.now() / 1000) - new Date().getTimezoneOffset() * 60;
			var seconds = 86400 - (date % 86400);
			e.textContent = [("0" + Math.floor(seconds / 3600)).substr(-2, 2), ("0" + Math.floor((seconds % 3600) / 60)).substr(-2, 2), ("0" + (seconds % 60)).substr(-2, 2)].join(":")	
		}

		setTimeout(function () {
			setInterval(setDate, 1000);
		}, d % 1000);
		setDate();
	}

});