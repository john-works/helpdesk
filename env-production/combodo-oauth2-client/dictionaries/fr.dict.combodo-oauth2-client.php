<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

 /**
 * Localized data
 */

Dict::Add('FR FR', 'French', 'Français', [
	'Oauth2Client:UI:IDPParameters' => 'Paramètres pour le Fournisseur d\'Identité',
	'Oauth2Client:UI:AppURL' => 'URL de l\'application',
	'Oauth2Client:UI:AppURL+' => 'Cette URL doit être recopiée dans la configuration OAuth2 de votre fournisseur',
	'Oauth2Client:UI:LandingURL' => 'URL de retour',
	'Oauth2Client:UI:LandingURL+' => 'Cette URL doit être recopiée dans la configuration OAuth2 de votre fournisseur',

	'Oauth2Client:UI:Message:ValidationOK' => 'Jeton valide :<BR/>%1$s',
	'Oauth2Client:UI:Message:ValidationError' => 'Le jeton n\'a pas été validé : %1$s',

	'Oauth2Client:UI:Button:GetToken' => 'Affiche le jeton d\'accès. Le jeton est régénéré si nécessaire',
	'Oauth2Client:UI:Button:Authenticate' => 'Authentification sur l\'IdP',

	'Oauth2Client:UI:Error:RefreshTokenNotAvailable' => 'Le jeton de rafraichissement n\'est pas disponible',

	'Menu:Oauth2Client' => 'Client OAuth 2.0',
	'Menu:Oauth2Client+' => 'Client OAuth 2.0 générique',

	'Class:Oauth2Client' => 'Client OAuth 2.0',
	'Class:Oauth2Client/Attribute:name' => 'Nom',
	'Class:Oauth2Client/Attribute:name+' => '',
	'Class:Oauth2Client/Attribute:provider' => 'Fournisseur',
	'Class:Oauth2Client/Attribute:provider+' => '',
	'Class:Oauth2Client/Attribute:description' => 'Description',
	'Class:Oauth2Client/Attribute:description+' => '',
	'Class:Oauth2Client/Attribute:client_id' => 'ID Client',
	'Class:Oauth2Client/Attribute:client_id+' => 'Recopier la chaîne fournie par votre fournisseur OAuth2',
	'Class:Oauth2Client/Attribute:client_secret' => 'Secret du Client',
	'Class:Oauth2Client/Attribute:client_secret+' => 'Recopier l\'information fournie par votre fournisseur OAuth2',
	'Class:Oauth2Client/Attribute:refresh_token' => 'Jeton de rafraîchissement',
	'Class:Oauth2Client/Attribute:refresh_token+' => '',
	'Class:Oauth2Client/Attribute:access_token' => 'Jeton d\'accès',
	'Class:Oauth2Client/Attribute:access_token+' => '',
	'Class:Oauth2Client/Attribute:refresh_token_expiration' => 'Expiration du Jeton de rafraîchissement',
	'Class:Oauth2Client/Attribute:refresh_token_expiration+' => 'Date après laquelle aucun jeton d\'accès expiré ne peut être renouvelé. Il faut re-valider le client Oauth2 avec votre fournisseur d\'identité.',
	'Class:Oauth2Client/Attribute:access_token_expiration' => 'Expiration du Jeton d\'accès',
	'Class:Oauth2Client/Attribute:access_token_expiration+' => 'Date après laquelle le jeton d\'accès expire. iTop va le renouveler automatiquement avec le fournisseur d\'identité (si c\'est supporté par l\'application et l\'Idp)',
	'Class:Oauth2Client/Attribute:scope' => 'Scope',
	'Class:Oauth2Client/Attribute:scope+' => 'A la création de l\'objet, le scope, s\'il est laissé vide, est rempli automatiquement selon le fournisseur',
	'Class:Oauth2Client/Attribute:authorization_state' => 'Statut d\'Autorisation State',
	'Class:Oauth2Client/Attribute:authorization_state+' => '',
	'Class:Oauth2Client/Attribute:token_type' => 'Type de Token',
	'Class:Oauth2Client/Attribute:token_type+' => '',

	'Class:GitHubOauth2Client' => 'Client OAuth 2.0 GitHub',

	'Class:MicrosoftGraphOauth2Client' => 'Client OAuth 2.0 Microsoft Graph',
	'Class:MicrosoftGraphOauth2Client/Attribute:tenant' => 'Tenant',
	'Class:MicrosoftGraphOauth2Client/Attribute:tenant+' => 'Tenant ID de l\'application configurée',

	'Class:GoogleOauth2Client' => 'Client OAuth 2.0 Google',

	'Class:HeadlessOauth2Client' => 'Client OAuth 2.0 Headless',
	'Class:HeadlessOauth2Client/Attribute:username' => 'Nom d\'utilisateur',
	'Class:HeadlessOauth2Client/Attribute:username+' => 'Login de l\'utilisateur iTop',
	'Class:HeadlessOauth2Client/Attribute:password' => 'Mot de Passe',
	'Class:HeadlessOauth2Client/Attribute:password+' => 'Mot de Passe de l\'utilisateur iTop',
	'Class:HeadlessOauth2Client/Attribute:base_url' => 'URL de Base',
	'Class:HeadlessOauth2Client/Attribute:base_url+' => 'URL de l\'application iTop',
	'Class:HeadlessOauth2Client/Attribute:version' => 'Version',
	'Class:HeadlessOauth2Client/Attribute:version+' => '',

	'Class:KeycloakOauth2Client' => 'Client OAuth 2.0 Keycloak',
	'Class:KeycloakOauth2Client/Attribute:url' => 'URL',
	'Class:KeycloakOauth2Client/Attribute:url+' => 'URL de l\'application Keycloak',
	'Class:KeycloakOauth2Client/Attribute:realm' => 'Realm',
	'Class:KeycloakOauth2Client/Attribute:realm+' => 'Domaine (Realm) de l\'application configurée',

	'OAuth2Client:baseinfo' => 'Informations de base',
	'OAuth2Client:idp_info' => 'Paramètres venant du Fournisseur d\'Identité',
]);
