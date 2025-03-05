$(document).ready(function(){
  // header navigation active purpose
  
  // $('header ul li').click( function() {
  //   $(this).addClass('active').siblings().removeClass('active');
  // });

// desktop support toggle

  // $(".sprt-drp-dwn").click(function() {
  //   $(this).toggleClass("active");
  //   $(".sprt-drp-dwn ul").slideToggle();
  // });



  // home > bnr-pet-packages

  $('.form-sldr').slick({ 
    slidesToShow: 1,
    arrows: false,
    dots: false,
    fade: true,
    asNavFor: '.pet-srvcs',
  });
  $('.pet-srvcs').slick({
    cssEase: 'linear',
    asNavFor: '.form-sldr',
    slidesToShow: 6,
    slidesToScroll: 1,
    dots: false,
    autoPlay:true,
    autoplaySpeed: 1500,
    speed: 500,
    arrows: false, 
    centerMode: true,
    focusOnSelect: true,
    responsive: [
      {
        breakpoint: 700,
        settings:{
          arrows:true,
          slidesToShow: 5,
          slidesToScroll:1,
          centerMode: true,
          centerPadding:"20%",
        }
      },
      {
        breakpoint: 599,
        settings:{
          arrows:true,
          slidesToShow: 4,
          slidesToScroll:1,
          centerMode: true,
          centerPadding:"25%",
        }
      },
      {
        breakpoint: 480,
        settings:{
          arrows:true,
          slidesToShow: 3,
          slidesToScroll:1,
          centerMode: true,
          centerPadding:"25%",
        }
      },
      {
        breakpoint: 374,
        settings:{
          arrows:true,
          slidesToShow: 2,
          slidesToScroll:1,
          centerMode: true,
          centerPadding:"25%",
        }
      }
    ]
  });


  // on scrolling header will get box shadow effect
  $(window).scroll(function() {
    if ($(this).scrollTop() > 1){  
      $('header').addClass("sticky");
    }
    else{
      $('header').removeClass("sticky");
    }
  });

// hom-page > hm-bnr-sec > hm-ryt-slider

  $('.hm-ryt-slider').slick({
    dots: false,
    infinite: true,
    autoplay: true,
    autoplaySpeed: 5000,
    speed: 500,
    arrows: false,
    fade: true,
    cssEase: 'linear'
  });

// home > client-quot-sec > quot-slider 
  $('.quot-slider').slick({
    autoplay: true,
    autoplaySpeed: 2000,
    arrows:false,
    dots:false,
    slidesToShow: 4,
    slidesToScroll: 1,
    responsive: [
      {
        breakpoint: 1350,
        settings:{
          slidesToShow: 3,
        }
      },
      {
        breakpoint: 992,
        settings:{
          slidesToShow: 2,
        }
      },
      {
        breakpoint: 600,
        settings:{
          slidesToShow: 1,
        }
      },
    ]
  });

// home > srvprdct-sec > prdct-nav

  $('#prdct-nav li').click( function() {

    var tabID = $(this).attr('data-tab');
    
    $(this).addClass('active').siblings().removeClass('active');
    
    $('#tab-'+tabID).addClass('active').siblings().removeClass('active');

  });

// home > srvprdct-sec > pet-servs-sldr 

  $('.pet-servs-sldr').slick({
    infinite: false,
    arrows:true,
    dots:true,
    slidesToShow: 3,
    slidesToScroll: 1,
    responsive: [
      {
        breakpoint: 992,
        settings:{
          slidesToShow: 2,
        }
      },
      {
        breakpoint:600,
        settings:{
          slidesToShow: 2,
        }
      },
      {
        breakpoint:480,
        settings:{
          slidesToShow: 1,
        }
      },
    ]
  });
  
// home > featured-sec > we-are-in-logos-slider
  
  $('.we-are-in-logos-slider').slick({
    slidesToShow: 7,
    slidesToScroll: 1,
    infinite: true,
    arrows:false,
    dots:false,
    loop:true,
    autoplay: true,
    autoplaySpeed: 1500,
    responsive: [ 
      {
        breakpoint: 1599,
        settings:{
          slidesToShow: 6,
        }
      },
      {
        breakpoint: 1350,
        settings:{
          slidesToShow: 5,
        }
      },
      {
        breakpoint: 992,
        settings:{
          slidesToShow: 5,
        }
      }
      ,
      {
        breakpoint: 768,
        settings:{
          slidesToShow: 4,
        }
      },
      {
        breakpoint: 600,
        settings:{
          slidesToShow: 3,
        }
      },
      {
        breakpoint: 480,
        settings:{
          slidesToShow: 2,
        }
      }
    ]
  });

  // home > client-quot-sec > quot-slider
  $('.clnt-rvew-slider').slick({
    autoplay: true,
    autoplaySpeed: 2000,
    arrows:false,
    dots:false,
    slidesToShow: 4,
    slidesToScroll: 4,
    infinite: true,
    responsive: [
      {
        breakpoint: 1200,
        settings:{
          slidesToShow: 3,
        }
      },
      {
        breakpoint: 700,
        settings:{
          slidesToShow: 2,
        }
      },
      {
        breakpoint: 480,
        settings:{
          arrows:true,
          slidesToShow: 1,
        }
      },
    ]
  });

// home > happiness guarantee > hpyns-slider

  $('.hpyns-img-slider').slick({
    slidesToShow: 1,
    slidesToScroll: 1,
    swipe:false,
    arrows: false,
    fade: true,
    autoplay: true,
    autoplaySpeed: 2000,
    asNavFor: '.hpyns-slider'
  });
  $('.hpyns-slider').slick({
    slidesToShow: 8,
    slidesToScroll: 1,
    swipe: false,
    asNavFor: '.hpyns-img-slider',
    dots: false,
    centerMode: true,
    focusOnSelect: true
  });


// vet-service > vet-doc-slider  

  $('.vet-doc-slider').slick({
    autoplay: true,
    autoplaySpeed: 2000,
    arrows:true,
    dots:true,
    slidesToShow: 5,
    slidesToScroll: 2,
    infinite: false,
    responsive: [
      {
        breakpoint: 1599,
        settings:{
          slidesToShow: 4,
        }
      },
      {
        breakpoint: 1200,
        settings:{
          slidesToShow: 4,
        }
      },
      {
        breakpoint: 768,
        settings:{
          arrows:true,
          slidesToShow: 3,
        }
      },
      {
        breakpoint: 600,
        settings:{
          slidesToShow: 2,
        }
      },
      {
        breakpoint: 480,
        settings:{
          slidesToShow: 1,
        }
      }
    ]
  });

 
// vet-service > vet-srvc-slider  

  $('.vet-srvc-slider').slick({
    slidesToShow: 8,
    slidesToScroll: 3,
    infinite: true,
    arrows:true,
    dots:true,
    loop:true,
    autoplay: false,
    autoplaySpeed: 1500,
    responsive: [ 
      {
        breakpoint: 1919,
        settings:{
          slidesToShow: 7,
        }
      },
      {
        breakpoint: 1200,
        settings:{
          slidesToShow: 6,
        }
      },
      {
        breakpoint: 992,
        settings:{
          slidesToShow: 5,
        }
      }
      ,
      {
        breakpoint: 768,
        settings:{
          slidesToShow: 4,
        }
      },
      {
        breakpoint: 600,
        settings:{
          slidesToShow: 3,
        }
      },
      {
        breakpoint: 480,
        settings:{
          slidesToShow: 2,
        }
      }
    ]
  });

// below 992 screen js

jQuery(document).ready(function($){
  var width = $(window).width(); 
  if (width <= 991){
  
    $('.main-pckg').slick({
      infinite: true,
      slidesToShow: 3,
      slidesToScroll: 3,
      arrows: false,
      dots: false,
      adaptiveHeight: true,
      responsive: [
        {
          breakpoint: 767,
          settings: { 
            slidesToShow: 2,
            slidesToScroll: 1,
            infinite: true,
          }
        }
      ]
    });


  // mobile-menu

    $(".mb-menu-icon").click(function(){
      $(".mb-drp").slideToggle();
    });

  // mobile menu drop down

  $(".mb-drp li .menu_expand").click(function(){
    $(this).parent().find(".submenu").slideToggle();
    $(this).parent().siblings().find(".submenu").slideUp();
    $(this).parent().siblings().find(".menu_expand").removeClass('open');
    $(this).toggleClass('open');
  })

 

  }
});
 

});
 