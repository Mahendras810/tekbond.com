(function ($) {
  $(document).ready(function() {
    // Slide toggle for custom toggle button
    $('#customToggleButton').click(function() {
      $('#navbarMobileContent').slideToggle();
      $(this).toggleClass('active');
    });

    // Header fixed class based on scroll position
    $(window).scroll(function() {
      if ($(this).scrollTop() > 100) {
        $('header').addClass('fixed');
      } else {
        $('header').removeClass('fixed');
      }
    });

    // Add class to element by data attribute
    $('[data-drupal-selector="order-total-summary"]').addClass('col-md-3 total-order-sum');

    // Rearrange product elements for all products
    $('.views-row').each(function() {
      var container = $(this);
      var imageWrapper = container.find('.product--variation-field--variation_field_image');
      var titleElement = container.find('.field--name-title');
      var priceElement = container.find('.product--variation-field--variation_price');

      // Move image wrapper to the beginning
      container.prepend(imageWrapper);

      // Move title after image
      container.append(titleElement);

      // Move price after title
      container.append(priceElement);
    });

    // Initialize Owl Carousel for image thumbnails
    var $carousel = $('.image-thumbnails');
    var totalItems = $carousel.find('.thumbnail-image').length;

    // Initialize Owl Carousel
    $carousel.owlCarousel({
      items: 4, // Number of items you want to display
      loop: false, // Set to true if you want the carousel to loop
      margin: 10,
      nav: totalItems > 4, // Show navigation buttons only if more than 4 items
      dots: false,
      responsive: {
        0: {
          items: 2
        },
        600: {
          items: 3
        },
        1000: {
          items: 4
        }
      }
    });

    // Toggle navigation button visibility
    if (totalItems > 4) {
      $('.owl-nav').addClass('visible');
    } else {
      $('.owl-nav').removeClass('visible');
    }

    // Click event for thumbnail images
    $('.image-thumbnails .thumbnail-image img').click(function() {
      var newSrc = $(this).attr('src');
      $('#main-product-image').attr('src', newSrc);
    });

    // Show color name below circle on hover
    document.querySelectorAll('.form-item-purchased-entity-0-attributes-attribute-color label').forEach(function(label) {
      label.addEventListener('mouseenter', function() {
          const colorName = label.querySelector('.field--name-name');
          colorName.classList.add('show'); // Add class to show the name
      });
  
      label.addEventListener('mouseleave', function() {
          const colorName = label.querySelector('.field--name-name');
          colorName.classList.remove('show'); // Remove class to hide the name
      });
    });
    $(".connect-with-us-slider").owlCarousel({
        loop: true,
        margin: 30,
        nav: true,
        dots: false,
        thumbs: false,
        autoplay: false,
        smartSpeed: 1000,
        autoplayHoverPause: true,
        navText: [
          '<i class="fa fa-angle-left"></i>',
          '<i class="fa fa-angle-right"></i>'
        ],
        responsive: {
          0: { items: 1 },
          768: { items: 2 },
          992: { items: 3 },
          1200: { items: 3 }
        }
    });
  });
})(jQuery);