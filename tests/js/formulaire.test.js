/**
 * Tests structurels du formulaire de reservation (index.html).
 * Verifie que les garde-fous HTML5 attendus sont bien presents.
 * Execution : node --test tests/js/
 */

const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');

const html = fs.readFileSync(
	path.join(__dirname, '..', '..', 'index.html'), 'utf8'
);

/** Extrait la balise <input>/<select> portant l identifiant donne. */
function champ(id) {
	const m = html.match(new RegExp('<(?:input|select|textarea)[^>]*id="' + id + '"[^>]*>'));
	return m ? m[0] : null;
}

test('TS-01 - le formulaire de reservation existe et poste ses donnees', () => {
	const form = html.match(/<form[^>]*id="form-reservation"[^>]*>/);
	assert.ok(form, 'formulaire #form-reservation introuvable');
	assert.match(form[0], /method="post"/i);
});

test('TS-02 - le champ telephone est correctement type', () => {
	const t = champ('res-telephone');
	assert.ok(t, 'champ #res-telephone introuvable');
	assert.match(t, /type="tel"/);
	assert.match(t, /inputmode="numeric"/);
});

test('TS-03 - le champ telephone borne la saisie a 14 caracteres (10 chiffres + 4 espaces)', () => {
	assert.match(champ('res-telephone'), /maxlength="14"/);
});

test('TS-04 - le champ telephone affiche le format attendu en exemple', () => {
	assert.match(champ('res-telephone'), /placeholder="06 00 00 00 00"/);
});

test('TS-05 - les champs indispensables sont obligatoires', () => {
	for (const id of ['res-nom', 'res-email', 'res-cottage', 'res-arrivee']) {
		assert.match(champ(id), /required/, id + ' devrait etre requis');
	}
});

test('TS-06 - l adresse e-mail est validee par le navigateur', () => {
	assert.match(champ('res-email'), /type="email"/);
});

test('TS-07 - chaque champ possede un label associe', () => {
	for (const id of ['res-nom', 'res-email', 'res-telephone', 'res-cottage', 'res-arrivee']) {
		assert.match(html, new RegExp('<label for="' + id + '"'), 'label manquant pour ' + id);
	}
});

test('TS-08 - le script de formatage est bien charge par la page', () => {
	assert.match(html, /<script src="assets\/js\/telephone\.js"><\/script>/);
});
