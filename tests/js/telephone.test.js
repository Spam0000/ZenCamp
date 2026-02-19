/**
 * Tests unitaires du formatage du champ telephone.
 * Execution : node --test tests/js/
 */

const test = require('node:test');
const assert = require('node:assert');
const tel = require('../../assets/js/telephone.js');

test('TU-01 - un espace est insere apres chaque paire de chiffres', () => {
	assert.strictEqual(tel.formater('0600000000'), '06 00 00 00 00');
	assert.strictEqual(tel.formater('0102030405'), '01 02 03 04 05');
});

test('TU-02 - le formatage est progressif pendant la saisie', () => {
	assert.strictEqual(tel.formater('0'), '0');
	assert.strictEqual(tel.formater('06'), '06');
	assert.strictEqual(tel.formater('061'), '06 1');
	assert.strictEqual(tel.formater('06123'), '06 12 3');
});

test('TU-03 - les caracteres non numeriques sont ignores', () => {
	assert.strictEqual(tel.formater('06.12.34.56.78'), '06 12 34 56 78');
	assert.strictEqual(tel.formater('06-12-34-56-78'), '06 12 34 56 78');
	assert.strictEqual(tel.formater('abc06xy1234def5678'), '06 12 34 56 78');
});

test('TU-04 - la saisie est bornee a 10 chiffres', () => {
	assert.strictEqual(tel.formater('06000000009999'), '06 00 00 00 00');
	assert.strictEqual(tel.formater('0600000000').replace(/\D/g, '').length, 10);
});

test('TU-05 - le formatage est idempotent (retaper une valeur deja formatee)', () => {
	const une = tel.formater('0600000000');
	assert.strictEqual(tel.formater(une), une);
});

test('TU-06 - les cas limites ne provoquent pas d erreur', () => {
	assert.strictEqual(tel.formater(''), '');
	assert.strictEqual(tel.formater(null), '');
	assert.strictEqual(tel.formater(undefined), '');
	assert.strictEqual(tel.formater('   '), '');
});

test('TU-07 - le curseur reste apres le meme chiffre', () => {
	// « 06 12 34 » : 3 chiffres a gauche du curseur => position 4 ("06 1|")
	assert.strictEqual(tel.positionCurseur('06 12 34', 3), 4);
	// 2 chiffres a gauche => position 2 ("06|")
	assert.strictEqual(tel.positionCurseur('06 12 34', 2), 2);
	// aucun chiffre a gauche => debut du champ
	assert.strictEqual(tel.positionCurseur('06 12 34', 0), 0);
});

test('TU-08 - comptage des chiffres a gauche du curseur', () => {
	assert.strictEqual(tel.chiffresAvant('06 12 34', 4), 3);
	assert.strictEqual(tel.chiffresAvant('06 12 34', 0), 0);
	assert.strictEqual(tel.chiffresAvant('06 12 34', 8), 6);
});

test('TU-09 - insertion d un chiffre au milieu : la valeur reste coherente', () => {
	// L utilisateur place le curseur apres « 06 » et tape « 9 »
	const saisie = '069 12 34 56 78';
	assert.strictEqual(tel.formater(saisie), '06 91 23 45 67');
});

test('TU-10 - brancher() est sans effet si le champ est absent', () => {
	assert.strictEqual(tel.brancher(null), false);
});
