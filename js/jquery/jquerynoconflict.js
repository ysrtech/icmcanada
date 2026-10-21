jQuery.noConflict();
jQuery(document).ready(function($){
 $('a[href=\\#top]').click(function(){
        $('html, body').animate({scrollTop:0}, 'slow');
        return false;
    });

    $('a[href*=\\#]').click(function() {
 if (location.pathname.replace(/^\//,'') == this.pathname.replace(/^\//,'')
 && location.hostname == this.hostname) {
   var $target = $(this.hash);
   $target = $target.length && $target
   || $('[name=' + this.hash.slice(1) +']');
   if ($target.length) {
  var targetOffset = $target.offset().top;
  $('html,body')
  .animate({scrollTop: targetOffset}, 900);
    return false;
   }
  }
  });

});