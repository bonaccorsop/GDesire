<?

	require_once 'gdesire_class.php';

	$gd = new GDesire('images/img.jpg');

	$gd->resize(200,200)
		->save('images/img200.jpg')
		->greize()
		->save('images/img200_greized.jpg');


?>