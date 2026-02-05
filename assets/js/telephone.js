/* ZenCamp - Formatage du champ telephone.
 *
 * Regroupe les chiffres saisis par paires : 0600000000 -> 06 00 00 00 00
 * Le module est expose a la fois sur window (navigateur) et sur
 * module.exports (Node) afin de pouvoir etre teste hors navigateur.
 */
(function(racine) {

	'use strict';

	var LONGUEUR_MAX = 10; // numero francais : 10 chiffres

	/**
	 * Ne conserve que les chiffres, limite a 10 et insere un espace
	 * apres chaque paire.
	 * @param {string} saisie
	 * @returns {string}
	 */
	function formater(saisie) {

		var chiffres = String(saisie == null ? '' : saisie)
			.replace(/\D/g, '')
			.slice(0, LONGUEUR_MAX);

		return chiffres.replace(/\d{2}(?=\d)/g, '$& ');

	}

	/**
	 * Position du curseur apres reformatage : on conserve le nombre de
	 * chiffres situes a sa gauche.
	 * @param {string} valeurFormatee
	 * @param {number} chiffresAvant
	 * @returns {number}
	 */
	function positionCurseur(valeurFormatee, chiffresAvant) {

		var i = 0,
			compte = 0;

		while (i < valeurFormatee.length && compte < chiffresAvant) {
			if (/\d/.test(valeurFormatee[i]))
				compte++;
			i++;
		}

		return i;

	}

	/** Nombre de chiffres presents avant la position donnee. */
	function chiffresAvant(valeur, position) {
		return (valeur.slice(0, position).match(/\d/g) || []).length;
	}

	/** Branche le formatage sur un champ <input>. */
	function brancher(champ) {

		if (!champ)
			return false;

		champ.addEventListener('input', function() {

			var avant = chiffresAvant(champ.value, champ.selectionStart),
				valeur = formater(champ.value);

			champ.value = valeur;
			champ.setSelectionRange(
				positionCurseur(valeur, avant),
				positionCurseur(valeur, avant)
			);

		});

		return true;

	}

	var api = {
		formater: formater,
		positionCurseur: positionCurseur,
		chiffresAvant: chiffresAvant,
		brancher: brancher,
		LONGUEUR_MAX: LONGUEUR_MAX
	};

	if (typeof module === 'object' && module.exports)
		module.exports = api;
	else {
		racine.ZenCampTelephone = api;
		document.addEventListener('DOMContentLoaded', function() {
			api.brancher(document.getElementById('res-telephone'));
		});
	}

})(typeof window !== 'undefined' ? window : this);
