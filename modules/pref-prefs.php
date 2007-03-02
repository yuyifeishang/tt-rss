<?php
	function prefs_js_redirect() {
		print "<html><body>
			<script type=\"text/javascript\">
				window.location = 'prefs.php';
			</script>
			</body></html>";
	}

	function module_pref_prefs($link) {
		$subop = $_REQUEST["subop"];

		if ($subop == "Save configuration") {

			$_SESSION["prefs_op_result"] = "save-config";

			$_SESSION["prefs_cache"] = false;

			foreach (array_keys($_POST) as $pref_name) {
			
				$pref_name = db_escape_string($pref_name);
				$value = db_escape_string($_POST[$pref_name]);

				$result = db_query($link, "SELECT type_name 
					FROM ttrss_prefs,ttrss_prefs_types 
					WHERE pref_name = '$pref_name' AND type_id = ttrss_prefs_types.id");

				if (db_num_rows($result) > 0) {

					$type_name = db_fetch_result($result, 0, "type_name");

//					print "$pref_name : $type_name : $value<br>";

					if ($type_name == "bool") {
						if ($value == "1") {
							$value = "true";
						} else {
							$value = "false";
						}
					} else if ($type_name == "integer") {
						$value = sprintf("%d", $value);
					}

//					print "$pref_name : $type_name : $value<br>";

					db_query($link, "UPDATE ttrss_user_prefs SET value = '$value' 
						WHERE pref_name = '$pref_name' AND owner_uid = ".$_SESSION["uid"]);

				}

			}

			return prefs_js_redirect();

		} else if ($subop == "getHelp") {

			$pref_name = db_escape_string($_GET["pn"]);

			$result = db_query($link, "SELECT help_text FROM ttrss_prefs
				WHERE pref_name = '$pref_name'");

			if (db_num_rows($result) > 0) {
				$help_text = db_fetch_result($result, 0, "help_text");
				print $help_text;
			} else {
				print "Unknown option: $pref_name";
			}

		} else if ($subop == "Change e-mail") {

			$email = db_escape_string($_GET["email"]);
			$active_uid = $_SESSION["uid"];

			if ($email) {
				db_query($link, "UPDATE ttrss_users SET email = '$email' 
						WHERE id = '$active_uid'");				
			}

			return prefs_js_redirect();

		} else if ($subop == "Change password") {

			$old_pw = $_POST["OLD_PASSWORD"];
			$new_pw = $_POST["OLD_PASSWORD"];

			$old_pw_hash = 'SHA1:' . sha1($_POST["OLD_PASSWORD"]);
			$new_pw_hash = 'SHA1:' . sha1($_POST["NEW_PASSWORD"]);

			$active_uid = $_SESSION["uid"];

			if ($old_pw && $new_pw) {

				$login = db_escape_string($_SERVER['PHP_AUTH_USER']);

				$result = db_query($link, "SELECT id FROM ttrss_users WHERE 
					id = '$active_uid' AND (pwd_hash = '$old_pw' OR 
						pwd_hash = '$old_pw_hash')");

				if (db_num_rows($result) == 1) {
					db_query($link, "UPDATE ttrss_users SET pwd_hash = '$new_pw_hash' 
						WHERE id = '$active_uid'");				

					$_SESSION["pwd_change_result"] = "ok";
				} else {
					$_SESSION["pwd_change_result"] = "failed";					
				}
			}

			return prefs_js_redirect();

		} else if ($subop == "Reset to defaults") {

			$_SESSION["prefs_op_result"] = "reset-to-defaults";

			if (DB_TYPE == "pgsql") {
				db_query($link,"UPDATE ttrss_user_prefs 
					SET value = ttrss_prefs.def_value 
					WHERE owner_uid = '".$_SESSION["uid"]."' AND
					ttrss_prefs.pref_name = ttrss_user_prefs.pref_name");
			} else {
				db_query($link, "DELETE FROM ttrss_user_prefs 
					WHERE owner_uid = ".$_SESSION["uid"]);
				initialize_user_prefs($link, $_SESSION["uid"]);
			}

			return prefs_js_redirect();

		} else if ($subop == "Change theme") {

			$theme = db_escape_string($_POST["theme"]);

			if ($theme == "Default") {
				$theme_qpart = 'NULL';
			} else {
				$theme_qpart = "'$theme'";
			}

			$result = db_query($link, "SELECT id,theme_path FROM ttrss_themes
				WHERE theme_name = '$theme'");

			if (db_num_rows($result) == 1) {
				$theme_id = db_fetch_result($result, 0, "id");
				$theme_path = db_fetch_result($result, 0, "theme_path");
			} else {
				$theme_id = "NULL";
				$theme_path = "";
			}

			db_query($link, "UPDATE ttrss_users SET
				theme_id = $theme_id WHERE id = " . $_SESSION["uid"]);

			$_SESSION["theme"] = $theme_path;

			return prefs_js_redirect();

		} else {

//			print check_for_update($link);

			if (!SINGLE_USER_MODE) {

				$result = db_query($link, "SELECT id,email FROM ttrss_users
					WHERE id = ".$_SESSION["uid"]." AND (pwd_hash = 'password' OR
						pwd_hash = 'SHA1:".sha1("password")."')");

				if (db_num_rows($result) != 0) {
					print format_warning("Your password is at default value, please change it.");
				}

				if ($_SESSION["pwd_change_result"] == "failed") {
					print format_warning("Could not change the password.");
				}

				if ($_SESSION["pwd_change_result"] == "ok") {
					print format_notice("Password was changed.");
				}

				$_SESSION["pwd_change_result"] = "";

				if ($_SESSION["prefs_op_result"] == "reset-to-defaults") {
					print format_notice("The configuration was reset to defaults.");
				}

				if ($_SESSION["prefs_op_result"] == "save-config") {
					print format_notice("The configuration was saved.");
				}

				$_SESSION["prefs_op_result"] = "";

				print "<form action=\"backend.php\" method=\"GET\">";
	
				print "<table width=\"100%\" class=\"prefPrefsList\">";
	 			print "<tr><td colspan='3'><h3>Personal data</h3></tr></td>";

				$result = db_query($link, "SELECT email FROM ttrss_users
					WHERE id = ".$_SESSION["uid"]);
					
				$email = db_fetch_result($result, 0, "email");
	
				print "<tr><td width=\"40%\">E-mail</td>";
				print "<td><input class=\"editbox\" name=\"email\" 
					value=\"$email\"></td></tr>";
	
				print "</table>";
	
				print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";
	
				print "<p><input class=\"button\" type=\"submit\" 
					value=\"Change e-mail\" name=\"subop\">";

				print "</form>";

				print "<form action=\"backend.php\" method=\"POST\" name=\"changePassForm\">";
	
				print "<table width=\"100%\" class=\"prefPrefsList\">";
	 			print "<tr><td colspan='3'><h3>Authentication</h3></tr></td>";
	
				print "<tr><td width=\"40%\">Old password</td>";
				print "<td><input class=\"editbox\" type=\"password\"
					name=\"OLD_PASSWORD\"></td></tr>";
	
				print "<tr><td width=\"40%\">New password</td>";
				
				print "<td><input class=\"editbox\" type=\"password\"
					name=\"NEW_PASSWORD\"></td></tr>";
	
				print "</table>";
	
				print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";
	
				print "<p><input class=\"button\" type=\"submit\" 
					onclick=\"return validateNewPassword(this.form)\"
					value=\"Change password\" name=\"subop\">";
	
				print "</form>";

			}

			$result = db_query($link, "SELECT
				theme_id FROM ttrss_users WHERE id = " . $_SESSION["uid"]);

			$user_theme_id = db_fetch_result($result, 0, "theme_id");

			$result = db_query($link, "SELECT
				id,theme_name FROM ttrss_themes ORDER BY theme_name");

			if (db_num_rows($result) > 0) {

				print "<form action=\"backend.php\" method=\"POST\">";
				print "<table width=\"100%\" class=\"prefPrefsList\">";
	 			print "<tr><td colspan='3'><h3>Themes</h3></tr></td>";
				print "<tr><td width=\"40%\">Select theme</td>";
				print "<td><select name=\"theme\">";
				print "<option>Default</option>";
				print "<option disabled>--------</option>";				
				
				while ($line = db_fetch_assoc($result)) {	
					if ($line["id"] == $user_theme_id) {
						$selected = "selected";
					} else {
						$selected = "";
					}
					print "<option $selected>" . $line["theme_name"] . "</option>";
				}
				print "</select></td></tr>";
				print "</table>";
				print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";
				print "<p><input class=\"button\" type=\"submit\" 
					value=\"Change theme\" name=\"subop\">";
				print "</form>";
			}

			initialize_user_prefs($link, $_SESSION["uid"]);

			$result = db_query($link, "SELECT 
				ttrss_user_prefs.pref_name,short_desc,help_text,value,type_name,
				section_name,def_value
				FROM ttrss_prefs,ttrss_prefs_types,ttrss_prefs_sections,ttrss_user_prefs
				WHERE type_id = ttrss_prefs_types.id AND 
					section_id = ttrss_prefs_sections.id AND
					ttrss_user_prefs.pref_name = ttrss_prefs.pref_name AND
					owner_uid = ".$_SESSION["uid"]."
				ORDER BY section_id,short_desc");

			print "<form action=\"backend.php\" method=\"POST\">";

			$lnum = 0;

			$active_section = "";
	
			while ($line = db_fetch_assoc($result)) {

				if ($active_section != $line["section_name"]) {

					if ($active_section != "") {
						print "</table>";
					}

					print "<p><table width=\"100%\" class=\"prefPrefsList\">";
				
					$active_section = $line["section_name"];				
					
					print "<tr><td colspan=\"3\"><h3>$active_section</h3></td></tr>";
//					print "<tr class=\"title\">
//						<td width=\"25%\">Option</td><td>Value</td></tr>";

					$lnum = 0;
				}

//				$class = ($lnum % 2) ? "even" : "odd";

				print "<tr>";

				$type_name = $line["type_name"];
				$pref_name = $line["pref_name"];
				$value = $line["value"];
				$def_value = $line["def_value"];
				$help_text = $line["help_text"];

				print "<td width=\"40%\" id=\"$pref_name\">" . $line["short_desc"];

				if ($help_text) print "<div class=\"prefHelp\">$help_text</div>";
				
				print "</td>";

				print "<td>";

				if ($type_name == "bool") {
//					print_select($pref_name, $value, array("true", "false"));

					if ($value == "true") {
						$value = "Yes";
					} else {
						$value = "No";
					}

					print_radio($pref_name, $value, array("Yes", "No"));
			
				} else {
					print "<input class=\"editbox\" name=\"$pref_name\" value=\"$value\">";
				}

				print "</td>";

				print "</tr>";

				$lnum++;
			}

			print "</table>";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";

			print "<p><input class=\"button\" type=\"submit\" 
				name=\"subop\" value=\"Save configuration\">";
				
			print "&nbsp;<input class=\"button\" type=\"submit\" 
				name=\"subop\" onclick=\"return validatePrefsReset()\" 
				value=\"Reset to defaults\"></p>";

			print "</form>";

		}
	}
?>
