/** 	=========================
	Template Name 	 : ombe
	Author			 : DexignZone
	Version			 : 1.0
	File Name		 : custom.js
	Author Portfolio : https://themeforest.net/user/dexignzone/portfolio

	Core script to handle the entire theme and core functions
**/

/* JavaScript Document */
jQuery(document).ready(function() {
    'use strict';
	
    // Get Started ==========
	if(jQuery('.get-started').length > 0){
		var swiperGetStarted = new Swiper(".get-started", {
			speed: 1500,
			parallax: true,
			slidesPerView: "auto",
			spaceBetween: 0,
			loop:true,
			effect: "fade",
			autoplay: {
				delay: 1500,
			},
			pagination: {
                el: ".swiper-pagination",
                clickable: true,
			},
		});
	}
	
	// Overlay Swiper ===
	if(jQuery('.overlay-swiper1').length > 0){
		var swiper = new Swiper('.overlay-swiper1', {
			speed: 1500,
			spaceBetween: 20,
			slidesPerView: "auto",
			loop:true,
			autoplay: {
				delay: 1500,
			},
		});
	}

	// Categories Swiper ===
	if(jQuery('.categories-swiper').length > 0){
		var swiper = new Swiper('.categories-swiper', {
			speed: 1500,
			spaceBetween: 18,
			slidesPerView: "auto",
			loop:true,
			// autoplay: {
			// 	delay: 2500,
			// },
		});
	}

	// Custom Swipable Tabs ===
	if(jQuery('.dz-tabs-swiper').length > 0){
		var swiper = new Swiper(".mySwiper", {
			slidesPerView: "auto",
			centeredSlides: false,
			initialSlide: 0,
		});
		var swiper2 = new Swiper(".mySwiper2", {
			spaceBetween: 15,
			thumbs: {
			  swiper: swiper,
			},
		});
	}

	// Product Detail Swiper ===
	if(jQuery('.product-detail-swiper').length > 0){
		var swiper = new Swiper('.product-detail-swiper', {
			speed: 1500,
			slidesPerView: 1,
			spaceBetween: 0,
			loop: true,
			effect: "fade",
			autoplay: {
				delay: 2000,
			},
		});
	}

	
	if(jQuery('.location-swiper').length > 0){
		var locationSwiper = new Swiper('.location-swiper', {
			speed: 300,
			slidesPerView: "auto",
			spaceBetween: 12,
			initialSlide: 0,
		});
		var locationSwiper2 = new Swiper(".location-swiper2", {
			spaceBetween: 18,
			thumbs: {
			  swiper: locationSwiper,
			},
		});
	}

	if(jQuery('.overlay-swiper2').length > 0){
		var swiper = new Swiper('.overlay-swiper2', {
			speed: 500,
			slidesPerView: 'auto',
			spaceBetween: 20,
		});
	}

	if(jQuery('.payment-card-swiper').length > 0){
		var swiper = new Swiper('.payment-card-swiper', {
			speed: 200,
			slidesPerView: 1.2,
			spaceBetween: 10,
		});
	}
});
/* Document .ready END */