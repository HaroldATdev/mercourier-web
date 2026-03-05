<?php 
// Evitar duplicación de login usando nonce único por request
static $wpcfe_login_loaded = false;

if ( $wpcfe_login_loaded ) {
	return; // Ya fue renderizado, salir
}

$wpcfe_login_loaded = true;
?>
<div class="row">
	<div class="col-md-4 offset-md-4">
		<!-- Material form login -->
		<?php $user_name 	= ( !empty( $_POST ) && array_key_exists( 'billing_email', $_POST  ) ) ? $_POST['billing_email'] : '' ; ?>
		<?php if( isset( $_GET['login'] ) && $_GET['login'] == 'failed' ): ?>
			<?php $user_name 	= isset( $_GET['user'] ) ? $_GET['user'] : '' ; ?>
			<div class="alert alert-danger" role="alert">
				<span><b><?php esc_html_e( 'Error', 'wpcargo-frontend-manager' ); ?> - </b> <?php echo apply_filters( 'wpcfe_login_error', esc_html__( 'Please check your Username or Password.', 'wpcargo-frontend-manager' ) ); ?></span>
			</div>
		<?php endif; ?>
		<div class="card">
			<h5 class="card-header primary-color-dark darken-2 white-text text-center py-4">
				<strong><?php esc_html_e( 'Sign in', 'wpcargo-frontend-manager' ); ?></strong>
			</h5>
			<!--Card content-->
			<div class="card-body px-lg-5 pt-0">
				<div class="my-2 text-center">
					<?php $site_logo = $wpcargo->logo ? '<img style="width:160px;" src="'.$wpcargo->logo.'" alt="Site Logo">' : '<h1 class="h3">'.get_bloginfo( 'name' ).'</h1>' ; ?>
					<a href="<?php echo get_bloginfo( 'url' ); ?>"><?php echo $site_logo; ?></a>
				</div>
				<?php do_action( 'wpcfe_before_login_form' ); ?>
				<!-- Form -->
				<form name="loginform" id="loginform" action="<?php echo site_url( '/wp-login.php' ); ?>" method="post">
					<!-- Email -->
					<div class="md-form login-username">
						<label class="form-check-label" for="user_login"><?php esc_html_e( 'Username/E-mail', 'wpcargo-frontend-manager' ); ?></label>
						<input id="user_login" class="form-control border-input" type="text" size="20" value="<?php echo $user_name; ?>" name="log" required="required">
					</div>
					<!-- Password -->
					<div class="md-form login-password" style="position: relative;">
 				      <label class="form-check-label" for="user_pass"><?php esc_html_e( 'Password', 'wpcargo-frontend-manager' ); ?></label>
                      <input id="user_pass" class="form-control border-input" type="password" size="20" value="" name="pwd" required="required">
                      <span id="toggle-password" style="position: absolute; right: 10px; bottom: 8px; cursor: pointer;">
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                          <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                          <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                        </svg>
                      </span>
                    </div>
					<?php if( has_action('register_form') ): ?>
					<div class="col-lg-12 p-0">
						<?php do_action( 'register_form' ); ?>
					</div>
					<?php endif ?>
					<div class="d-flex justify-content-around">
						<div>
							<!-- Remember me -->
							<div class="form-check">
                              <input name="rememberme" type="checkbox" id="rememberme" class="form-check-input" value="forever"
                                <?php echo ( isset( $_POST['rememberme'] ) ? 'checked' : '' ); ?>>
                              <label class="form-check-label" for="rememberme"><?php esc_html_e( 'Remember me', 'wpcargo-frontend-manager' ); ?></label>
                            </div>
						</div>
						<div>
							<a href="<?php echo wp_lostpassword_url( $redirect_to ); ?>"><?php esc_html_e( 'Forgot password?', 'wpcargo-frontend-manager' ); ?></a>
						</div>
					</div>
					<div class="md-form login-submit">
						<input type="hidden" value="<?php echo esc_attr( apply_filters( 'wpcfe_login_redirect', $redirect_to ) ); ?>" name="redirect_to">
						<p class="text-center small text-muted mb-3">
							Al usar el servicio acepto los <a href="https://www.mercourier.com/terminos" target="_blank" rel="noopener noreferrer">Términos y Condiciones</a>
						</p>
						<button id="wp-submit" class="btn btn-outline-primary btn-rounded btn-block my-4 waves-effect z-depth-0" type="submit" name="wp-submit"><?php esc_html_e('Login', 'wpcargo-frontend-manager' ); ?></button>
					</div>
				</form>
				<!-- Form -->
				<?php do_action( 'wpcfe_after_login_form' ); ?>				
			</div>
		</div>
		<!-- Material form login -->
	</div>
<style>
    #loginform .md-form label {
        position: static !important;
        transform: none !important;
        font-size: 0.9rem !important;
        color: #757575 !important;
        display: block !important;
        margin-bottom: 4px !important;
    }

    #loginform .md-form input.form-control {
        margin-top: 0 !important;
        border-radius: 4px !important;
    }
</style>
<script>
    document.getElementById('toggle-password').addEventListener('click', function () {
        const input = document.getElementById('user_pass');
        const icon = document.getElementById('eye-icon');

        if (input.type === 'password') {
            input.type = 'text';
            // Ojo tachado (ocultar)
            icon.innerHTML = `
                <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/>
                <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/>
                <path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709z"/>
                <path fill-rule="evenodd" d="M13.646 14.354l-12-12 .708-.708 12 12-.708.708z"/>
            `;
        } else {
            input.type = 'password';
            // Ojo normal (mostrar)
            icon.innerHTML = `
                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
            `;
        }
    });
</script>
</div>
