<?php
/**
 * LearnDash Group Invitation URL Core Class
 *
 * @package Freedomology
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LearnDash Group Invitation URL class.
 */
class LearnDash_Group_Invitation_URL {

	/**
	 * Singleton instance.
	 *
	 * @var LearnDash_Group_Invitation_URL|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return LearnDash_Group_Invitation_URL
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Render the invitation URL component.
	 *
	 * @param array $settings Elementor settings for dropdown and button text.
	 * @return string HTML output for the invitation URL.
	 */
	public function render_invitation_url( $settings = array() ) {

		$dropdown_label   = isset( $settings['dropdown_label'] ) ? esc_html( $settings['dropdown_label'] ) : esc_html__( 'Select Group:', 'freedomology' );
		$copy_button_text = isset( $settings['copy_button_text'] ) ? esc_html( $settings['copy_button_text'] ) : esc_html__( 'Copy', 'freedomology' );
		$copied_text      = isset( $settings['copied_text'] ) ? esc_html( $settings['copied_text'] ) : esc_html__( 'Copied!', 'freedomology' );

		ob_start();
		?>
		<div class="ldgiu-invite-url-wrapper">
			<label for="ldgiu_group_select"><?php echo $dropdown_label; ?></label>
			<select id="ldgiu_group_select" class="ldgiu-group-select">
				<option value=""><?php esc_html_e( 'Select a Group', 'freedomology' ); ?></option>
				<!-- You can populate this dynamically if you want with PHP -->
			</select>

			<input type="text" id="ldgiu_invitation_url" class="ldgiu-invitation-url" readonly placeholder="<?php esc_attr_e( 'Invitation URL will appear here.', 'freedomology' ); ?>" />

			<button type="button" id="ldgiu_copy_button" class="ldgiu-copy-button"><?php echo $copy_button_text; ?></button>
			<span id="ldgiu_copied_text" style="display:none;"><?php echo $copied_text; ?></span>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const copyButton = document.getElementById('ldgiu_copy_button');
			const inputField = document.getElementById('ldgiu_invitation_url');
			const copiedText = document.getElementById('ldgiu_copied_text');

			if (copyButton && inputField) {
				copyButton.addEventListener('click', function() {
					inputField.select();
					document.execCommand('copy');
					copiedText.style.display = 'inline-block';
					setTimeout(function() {
						copiedText.style.display = 'none';
					}, 2000);
				});
			}
		});
		</script>
		<?php
		return ob_get_clean();
	}
}
