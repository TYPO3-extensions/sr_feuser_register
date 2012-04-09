function tx_srfeuserregister_encrypt(form) {
	var rsa = new RSAKey();
	rsa.setPublic(form.n.value, form.e.value);

		// For login forms
	if (typeof form.pass !== 'undefined') {
		var pass = form.pass.value;
		var cryptedPass = rsa.encrypt(pass);
		form.pass.value = '';
		if (cryptedPass) {
			form.pass.value = 'rsa:' + hex2b64(cryptedPass);
		}
	}
		// For password and password_again entry forms
	if (typeof form['FE[fe_users][password]'] !== 'undefined') {
		var password = form['FE[fe_users][password]'].value;
		form['FE[fe_users][password]'].value = '';
		if (password && password.length > 0) {
			var cryptedPassword = rsa.encrypt(password);
			if (cryptedPassword) {
				form['FE[fe_users][password]'].value = 'rsa:' + hex2b64(cryptedPassword);
			}
		}
	}
	if (typeof form['FE[fe_users][password_again]'] !== 'undefined') {
		var password_again = form['FE[fe_users][password_again]'].value;
		form['FE[fe_users][password_again]'].value = '';
		if (password_again && password_again.length > 0) {
			var cryptedPassword_again = rsa.encrypt(password_again);
			if (cryptedPassword_again) {
				form['FE[fe_users][password_again]'].value = 'rsa:' + hex2b64(cryptedPassword_again);
			}
		}
	}

	form.e.value = '';
	form.n.value = '';
}