<?php
# inkludiert js und css
script('test', 'script');
style('test', 'style');
?>

<div id="app">
	<div id="app-navigation">
		<?php print_unescaped($this->inc('navigation/index')); ?>
		<?php print_unescaped($this->inc('settings/index')); ?>
	</div>

	<div id="app-content">
		<div id="app-content-wrapper">
			<?php # inkludiert andere seiten, in dem falle die index content seite
			print_unescaped($this->inc('content/index')); 
			echo "test";
			?>
		</div>
	</div>
</div>

