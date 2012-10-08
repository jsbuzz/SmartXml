<?php
	$testimonials = new SmartXML(file_get_contents("testimonials.hu.xml"));
	
	$i=0;
	foreach($testimonials->xpath->query("//testimonial") as $testimonial):
	?>
		<div class="testimonial<?=($i++ == 0 ? " first" : "") ?>">
			<h3><?= $testimonial->xpath("string(./title)") ?></h3>
			<p><?= $testimonial->xpath("./short")->first()->nodeValue; /* the first of a nodeList works as well */ ?></p>
			<div class="body">
				<p><strong><?= $testimonial->xpath("string(./title)") ?></strong></p>
				<?= $testimonial->xpath("string(./body)") ?>
			</div>
		</div>
	<? endforeach; ?>
