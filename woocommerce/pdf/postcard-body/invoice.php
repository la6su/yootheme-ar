<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<?php do_action( 'wpo_wcpdf_before_document', $this->get_type(), $this->order ); ?>
<style>
	/* Load font */
@font-face {
    font-family: 'YesevaOne-Regular';
    font-style: normal;
    font-weight: normal;
    src: local('YesevaOne-Regular'), local('YesevaOne-Regular'), url(<?php echo $this->get_template_path(); ?>/fonts/YesevaOne-Regular.ttf) format('truetype');
}
</style>

<table class="head container">
	<tr>
		<td class="shop-info">
			<div class="shop-name">
				<h2 style="font-family: 'YesevaOne-Regular', serif; font-weight: normal; font-size: 47px; line-height: 1; color: #6268cc">Дарим цветы и&nbsp;улыбки</h2>
			</div>
		</td>
		<td class="leftcolmn">
		<?php
		if ( $this->has_header_logo() ) {
			$this->header_logo();
		} else {
			echo $this->get_title();
		}
		?>
		</td>
	</tr>
</table>
<div style="text-align: center">
	<img src="https://tsvetvill.ru/wp-content/uploads/card-target.png" style="width: 168mm; height: 168mm;">
</div>
<div class="bottom-spacer"></div>
<div id="footer">
	<table class="body container">
		<tr>
			<td class="leftcolmn">
				<?php do_action( 'wpo_wcpdf_after_document_label', $this->get_type(), $this->order ); ?>
			</td>
			
			<td class="details-info">
			  <h3 style="font-family: 'YesevaOne-Regular', serif; font-weight: normal; font-size: 21px; margin-bottom: 20px">Инструкция для просмотра:</h3>
				<ul style="line-height: 1; font-size: 16px;">
					<li>1. Наведите камеру телефона на QR-код.</li>
					<li>2. Перейдите на страницу AR-открытки.</li>
					<li>3. Разрешите доступ к камере телефона.</li>
					<li>4. Наведите камеру на изображение, <br>чтобы увидеть ваше поздравление.</li>
				</ul>
			</td>
		</tr>
	</table>
</div>