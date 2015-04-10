inputChecked = null;
$("document").ready(function(){
	$("input[name=type]:radio").prop('checked', false);
	 
	$("#inputConference").change(function () {
		if(inputChecked !== null)
		{
			$("#infosAuteur").slideToggle('slow');
		}
		$("#infosConference").slideToggle('fast');
		inputChecked = "conference";
	});

	$("#inputAuthor").change(function () {
		$("#infosAuteur").slideToggle('slow');
		if(inputChecked !== null)
		{
			$("#infosConference").slideToggle('slow');
		}
		inputChecked = "author";
	});
});

function submit(){
	if (inputChecked == "author") {
		$nom = $.trim($("#nomAuteur").val());
		$prenom = $.trim($("#prenomAuteur").val());
		if($nom.length == 0)
		{
			$("#nomAuteur").addClass("invalidInput");
		}
		else
		{
			$("#nomAuteur").removeClass("invalidInput");
		}
		if($prenom.length == 0)
		{
			$("#prenomAuteur").addClass("invalidInput");
		}
		else
		{
			$("#prenomAuteur").removeClass("invalidInput");
		}
		if($nom.length != 0 && $prenom.length != 0)
		{
			checkAuthor($nom, $prenom);
		}
	}
	else if (inputChecked == "conference") {
		$conf = $.trim($("#nomConference").val());
		if($conf.length == 0)
		{
			$("#nomConference").addClass("invalidInput");
		}
		else
		{
			$("#nomConference").removeClass("invalidInput");
			checkConference($conf);
		}
	}	
}
$(document).on('click', '#submitCreateMap',submit);

/* Envoi pour vérification */
function checkAuthor($nom, $prenom){
	etat = 'Vérification de l\'auteur en cours.';
	ajaxFirstStart();
	setPropositionsTitle(etat);
	jQuery.ajax({
	    type: "POST",
	    url: Routing.generate('db_mashup_checkAuthor'),
		data: {nom: $nom, prenom: $prenom}})
	.done(function (data) {
		console.log("SUCCESScA");
		ajaxFirstComplete();
		switch(data.response)
    	{
    		case 'OK':
    			// Envoi du lien au parser
    			parserAuthorDirect(data.data);
    			break;
    		// 'KO'
    		default:
    			if(data.data.length == 0)
				{
    				title = 'L\'auteur \"' + $prenom + ' ' + $nom 
    						+ '\" n\'a pas été trouvé.';
    				setPropositionsTitle(title);
				}
    			else
    			{
	    			title = 'Plusieurs correspondances ont été trouvées.<br/>'
	    							+'Veuillez sélectionner un auteur: ';
	    			propositions = '<div class="suppr">'
						+ '<ul>';
	    			for (i = 0; i < data.data.length; i++) {
					propositions += '<li class="redirectionAuthor" href="" url="'
							+ data.data[i].url
							+ '">'
							+ data.data[i].author + '</li>';
	    			}
	    			propositions += '</ul></div>';
	    			setPropositionsTitle(title);
	    			afficherPropositions(propositions);
    			}
    			break;
    	}
	})
	.fail (function(jqXHR, textStatus ){
		console.log("Erreur: "+textStatus);
		ajaxFirstComplete();
	});
}

$(document).on('click', '.redirectionAuthor', parserAuthor);

/* L'utilisateur a selectionné un auteur ou la saisie a trouvé une unique correspondance */
function parserAuthor(){
	$link = $(this).attr('url');
	parserAuthorDirect($link);
}

// L'auteur saisi ou sélectionner est valide
function parserAuthorDirect($link){
	etat = 'Carte en cours de construction.<br/>'
		+'Vous serez notifié(e) par courriel lorsque la carte sera disponible.';
	setPropositionsTitle(etat);
	jQuery.ajax({
	    type: "POST",
	    url: Routing.generate('db_mashup_parserAuthor'),
		data: {link: $link}})
		.done(function (data) {
	    	console.log("SUCCESSpA");
		})
		.fail (function(jqXHR, textStatus ){
			console.log("Erreur: "+textStatus);
	});
}

function checkConference($nomConference){
	etat = 'Vérification de la conférence en cours.';
	ajaxFirstStart()
	setPropositionsTitle(etat);
	jQuery.ajax({
	    type: "POST",
	    url: Routing.generate('db_mashup_checkConference'),
		data: {nomConference: $nomConference}})
	.done(function (data) {
		console.log("SUCCESScC");
		ajaxFirstComplete();
    	switch(data.response)
    	{
    		case 'OK':
    			// Envoi du lien au parser
    			parserConferenceDirect(data.data);
    			break;
    		// 'KO'
    		default:
    			if(data.data.length == 0)
				{
    				title = 'La conference \"' + $nomConference 
    						+ '\" n\'a pas été trouvée.';
    				setPropositionsTitle(title);
				}
    			else
    			{
    				title = 'Plusieurs correspondances ont été trouvées.<br/>'
	    					+'Veuillez sélectionner une conférence: ';
	    					
    				propositions = '<div class="suppr">'
						+ '<ul>';
					for (i = 0; i < data.data.length; i++) {
						propositions += '<li class="redirectionConference" url="'
								+ data.data[i].url
								+ '">'
								+ data.data[i].conf + '</li>';
					}

					propositions += '</ul></div>';
					setPropositionsTitle(title);
	    			afficherPropositions(propositions);
    			}
    			break;
    	}
	})
	.fail (function(jqXHR, textStatus ){
		console.log("Erreur: "+textStatus);
		ajaxFirstComplete();
	});
}

$(document).on('click', '.redirectionConference', parserConference);

/* L'utilisateur a selectionné un auteur ou la saisie a trouvé une unique correspondance */
function parserConference(){
	$link = $(this).attr('url');
	parserConferenceDirect($link);
}

function parserConferenceDirect($link){
	etat = 'Carte en cours de construction.<br/>'
		+'Vous serez notifié(e) par courriel lorsque la carte sera disponible.';
	setPropositionsTitle(etat);
	jQuery.ajax({
	    type: "POST",
	    url: Routing.generate('db_mashup_parserConference'),
	    data: {nom: $conf}})
    .done(function (data){
	    	console.log("SUCCESSpC");
		})
	.fail (function(jqXHR, textStatus ){
			console.log("Erreur: "+textStatus);
		});
}

// Mise en place du loader, bloquage des input
function ajaxFirstStart() {
	console.log("start");
	clean();
	$( "input" ).prop( "disabled", true );
	$("#title").removeClass("hide").removeClass("show").addClass("show");
	$(".loader").removeClass("hide").removeClass("show").addClass("show");
};

// Arret du loader
function ajaxFirstComplete() {
	// Réactivation des input et disparition des loader, on efface aussi le contenu
	console.log("stop");
	$(".loader").removeClass("show").addClass("hide");
	$( "input" ).prop( "disabled", false );
};

// Enlève les messages d'affichage de réultats
function clean() {
	// On supprime les propositions ou le message précédents s'il y en a
	$(".suppr").each(function() {
		$(this).remove()
	});
	$("#propositions_title").empty();
	// On cache les balises éventuellements vides ou en cas de nouvelle recherches
	$("#propositions_body").removeClass("hide").removeClass("show").addClass("hide");
	$("#propositions_title").removeClass("hide").removeClass("show").addClass("hide");
}

//Affiche un message pour indiquer l'état de l'application
function afficherPropositions(message) {
	// On affiche la liste des propositions
	$("#propositions_body").removeClass("hide").removeClass("show").addClass("show");
	$("#propositions_body").append(message);
}

function setPropositionsTitle(prop_title){
	// On efface titre et body précédents avant d'afficher le nouveau titre
	//(et eventuellement le nouveau propositions_body s'il y en a)
	clean();
	$("#propositions_title").removeClass("hide").removeClass("show").addClass("show");
	$("#propositions_title").html(prop_title);
}
