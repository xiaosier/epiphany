<?php
include_once '../../src/Epi.php';
//include_once 'class.reviews.php';
Epi::setPath('base', '../../src');
Epi::init('route');
$router = new EpiRoute();
$router->post('/campgrounds/(\d+)/reviews.(xml|json)', array('Reviews', 'PostReview'));
$router->run(); 

class Reviews
{
  static public function PostReview($id, $format){
    var_dump($_POST);
  }
}
