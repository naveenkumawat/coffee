var OmbeUiSlider = function(){
	
	"use strict"
	
	/* Range ============ */
	var rangeslider = function(){
		
		function priceRangeSlider(elementId) {
			if($("#"+elementId).length > 0 ) {
				var tooltipSlider = document.getElementById(elementId);
				
				var formatForSlider = {
					from: function (formattedValue) {
						return Number(formattedValue);
					},
					to: function(numericValue) {
						return Math.round(numericValue);
					}
				};

				noUiSlider.create(tooltipSlider, {
					start: [18, 100],
					connect: true,
					format: formatForSlider,
					tooltips: [wNumb({decimals: 1}), true],
					range: {
						'min': 0,
						'max': 120
					}
				});
				
				tooltipSlider.noUiSlider.on('update', function (values, handle, unencoded) {
					jQuery("#"+elementId).parent().find('.slider-margin-value-min').html(values[0]);
					jQuery("#"+elementId).parent().find('.slider-margin-value-max').html("and " + values[1]);
				});
			}
		}
		priceRangeSlider("slider-tooltips");
	}
	/* Range ============ */
	var rangeslider2 = function(){
		
		function priceRangeSlider(elementId) {
			if($("#"+elementId).length > 0 ) {
				var tooltipSlider = document.getElementById(elementId);
				
				var formatForSlider = {
					from: function (formattedValue) {
						return Number(formattedValue);
					},
					to: function(numericValue) {
						return Math.round(numericValue);
					}
				};

				noUiSlider.create(tooltipSlider, {
					start: 18,
					connect: [true, false],
					format: formatForSlider,
					range: {
						'min': 18,
						'max': 100
					}
				});
				
				tooltipSlider.noUiSlider.on('update', function (values, handle, unencoded) {
					jQuery("#"+elementId).parent().find('.slider-margin-value-min').html("Up to  " + values + " kilometers only");
				});
			}
		}
		priceRangeSlider("slider-tooltips2");
	}
	/* Range ============ */
	var rangeslider3 = function(){
		
		function priceRangeSlider(elementId) {
			if($("#"+elementId).length > 0 ) {
				var tooltipSlider = document.getElementById(elementId);
				
				var formatForSlider = {
					from: function (formattedValue) {
						return Number(formattedValue);
					},
					to: function(numericValue) {
						return Math.round(numericValue);
					}
				};

				noUiSlider.create(tooltipSlider, {
					start: 40,
					connect: [true, false],
					format: formatForSlider,
					range: {
						'min': 10,
						'max': 100
					}
				});
				
				tooltipSlider.noUiSlider.on('update', function (values, handle, unencoded) {
					jQuery("#"+elementId).parents('.card, .dz-slider').find('.slider-margin-value-min').html(values + "km");
				});
			}
		}
		priceRangeSlider("slider-tooltips3");
	}
	
	/* Range ============ */
	var rangeslider4 = function(){
		
		function priceRangeSlider(elementId) {
			if($("#"+elementId).length > 0 ) {
				var tooltipSlider = document.getElementById(elementId);
				
				var formatForSlider = {
					from: function (formattedValue) {
						return Number(formattedValue);
					},
					to: function(numericValue) {
						return Math.round(numericValue);
					}
				};

				noUiSlider.create(tooltipSlider, {
					start: [18, 30],
					connect: true,
					format: formatForSlider,
					tooltips: [wNumb({decimals: 1}), true],
					range: {
						'min': 18,
						'max': 100
					}
				});
				
				tooltipSlider.noUiSlider.on('update', function (values, handle, unencoded) {
					jQuery("#"+elementId).parents('.card, .dz-slider').find('.slider-margin-value-min').html(values[0]);
					jQuery("#"+elementId).parents('.card, .dz-slider').find('.slider-margin-value-max').html(" - " + values[1]);
				});
			}
		}
		priceRangeSlider("slider-tooltips4");
	}

	/* Range ============ */
	var rangeslider5 = function(){
		
		function priceRangeSlider(elementId) {
			if($("#"+elementId).length > 0 ) {
				var tooltipSlider = document.getElementById(elementId);
				
				var formatForSlider = {
					from: function (formattedValue) {
						return Number(formattedValue);
					},
					to: function(numericValue) {
						return Math.round(numericValue);
					}
				};

				noUiSlider.create(tooltipSlider, {
					start: 40,
					connect: [true, false],
					format: formatForSlider,
					range: {
						'min': 100,
						'max': 200
					}
				});
				
				tooltipSlider.noUiSlider.on('update', function (values, handle, unencoded) {
					jQuery("#"+elementId).parents().find('.slider-margin-value-min').html(values + " cm");
				});
			}
		}
		priceRangeSlider("slider-tooltips5");
	}
	
	// Moving the slider by clicking pips  ============ 
	var handleSliderpips = function() {
		if(jQuery('#slider-pips').length > 0){
			var sliderLabels = document.getElementById('slider-labels');
			var pipsSlider = document.getElementById('slider-pips');
			noUiSlider.create(pipsSlider, {
				range: {
					min: 0,
					max: 100
				},
				start: [66],
				pips: {
					mode: 'values',
					values: [0, 33, 66, 100],
					density: 4
				}
				
			});

			var pips = pipsSlider.querySelectorAll('.noUi-value');
			function clickOnPip() {
				var value = Number(this.getAttribute('data-value'));
				pipsSlider.noUiSlider.set(value);
			}
			for (var i = 0; i < pips.length; i++) {

				// For this example. Do this in CSS!
				pips[i].style.cursor = 'pointer';
				pips[i].addEventListener('click', clickOnPip);
			}

			var activePips = [null, null];
			var pipSeries = ['Small','Medium','Large','Extra Large']
			pips.forEach(function(pip, index) {
				var label = document.createElement('span');
				label.className = 'slider-label';
				label.innerHTML = pipSeries[index];

				pip.innerHTML = label.innerHTML;

			});

			pipsSlider.noUiSlider.on('update', function (values, handle) {
				// Remove the active class from the current pip
				if (activePips[handle]) {
					activePips[handle].classList.remove('active-pip');
				}

				// Match the formatting for the pip
				var dataValue = Math.round(values[handle]);

				// Find the pip matching the value
				activePips[handle] = pipsSlider.querySelector('.noUi-value[data-value="' + dataValue + '"]');

				// Add the active class
				if (activePips[handle]) {
					activePips[handle].classList.add('active-pip');
				}
			});
		}
	}
	
	/* Function ============ */
	return{
		
		init:function(){
			rangeslider();
			rangeslider2();
			rangeslider3();
			rangeslider4();
			rangeslider5();
			handleSliderpips();
		},
		
		load:function(){
			
		},
		
		resize:function(){
			
		},
		
	}

}();

/* Document.ready Start */	
jQuery(document).ready(function() {
	'use strict';
	OmbeUiSlider.init();
});
/* Document.ready END */

/* Window Load START */
jQuery(window).on('load',function () {
	'use strict'; 
	OmbeUiSlider.load();
});
/*  Window Load END */

/* Window Resize START */
jQuery(window).on('resize',function () {
	'use strict'; 
	OmbeUiSlider.resize();
});
/*  Window Resize END */