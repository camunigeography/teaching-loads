<?php

# Teaching loads submission facility
require_once ('frontControllerApplication.php');
class teachingLoads extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'div' => 'teachingloads',
			'database' => 'teachingloads',
			'table' => 'teachingloads',
			'termsTable' => 'terms',
			'administrators' => true,
			'authentication' => true,		// All pages require authentication
			'academicStaffCallback' => NULL,		// NB Currently only a simple public function name supported
			'tabUlClass' => 'tabsflat',
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function assign additional actions
	public function actions ()
	{
		# Specify additional actions
		$actions = array (
			'people' => array (
				'description' => 'People',
				'tab' => 'People',
				'url' => 'people/',
			),
			'terms' => array (
				'description' => 'Data by term',
				'tab' => 'Terms',
				'url' => 'terms/',
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			
			-- Administrators
			CREATE TABLE `administrators` (
			  `username__JOIN__people__people__reserved` varchar(191) NOT NULL COMMENT 'Username',
			  `active` enum('','Yes','No') NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  PRIMARY KEY (`username__JOIN__people__people__reserved`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Helpdesk administrators';
			
			-- Teaching load submissions
			CREATE TABLE `teachingloads` (
			  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique key',
			  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp',
			  `username__JOIN__people__people__reserved` varchar(20) NOT NULL COMMENT 'Username',
			  `term__JOIN__teachingloads__terms__reserved` varchar(14) NOT NULL COMMENT 'Term',
			  `sabbatical` enum('Not on sabbatical','On sabbatical') NOT NULL DEFAULT 'Not on sabbatical' COMMENT 'On sabbatical?',
			  `fellowship` enum('Not on a Research Fellowship','On a Research Fellowship') NOT NULL DEFAULT 'Not on a Research Fellowship' COMMENT 'On a Research Fellowship?',
			  `lecturesUndergraduate` float(5,1) DEFAULT NULL COMMENT 'Lectures (undergraduate)',
			  `lecturesGraduate` float(5,1) DEFAULT NULL COMMENT 'Lectures (MPhil)',
			  `supervisions` float(5,1) DEFAULT NULL COMMENT 'Supervisions',
			  `practicals` float(5,1) DEFAULT NULL COMMENT 'Practicals',
			  `fieldtripsDay` float(5,1) DEFAULT NULL COMMENT 'Day fieldtrips (7 hours per day)',
			  `fieldtripsResidential` float(5,1) DEFAULT NULL COMMENT 'Residential fieldtrips (Day fieldtrips (56 hours per week)',
			  `talks` float(5,1) DEFAULT NULL COMMENT 'Talks',
			  `forums` float(5,1) DEFAULT NULL COMMENT 'Forums',
			  `workshops` float(5,1) DEFAULT NULL COMMENT 'Workshops',
			  `symposiums` float(5,1) DEFAULT NULL COMMENT 'Symposiums',
			  `committees` float(5,1) DEFAULT NULL COMMENT 'Committees',
			  `meetings` float(5,1) DEFAULT NULL COMMENT 'Meetings',
			  `other` float(5,1) DEFAULT NULL COMMENT 'Other',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Teaching loads';
			
			-- Terms
			CREATE TABLE `terms` (
			  `id` varchar(14) NOT NULL COMMENT 'Unique key',
			  `year` varchar(4) NOT NULL COMMENT 'Starting year (e.g. 2007 in the case of 07/08)',
			  `term` enum('','Michaelmas','Lent','Easter') NOT NULL COMMENT 'Term (used for ordering)',
			  `opening` date NOT NULL COMMENT 'Date when entries open for editing',
			  `closing` date NOT NULL COMMENT 'Date when entries close for editing',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Terms';
		";
	}
	
	
	# Additional initialisation
	public function main ()
	{
		# Load additional required libraries
		require_once ('timedate.php');
		
		# Get academic staff
		$callbackFunction = $this->settings['academicStaffCallback'];
		if (!$this->users = $callbackFunction ($this->databaseConnection)) {
			#!# Inform admin
			return false;
		}
		
		# Get the terms
		if (!$this->terms = $this->getTerms ()) {
			#!# Inform admin
			return false;
		}
	}
	
	
	# Welcome screen
	public function home ()
	{
		# Show the welcome message
		echo "\n<p>Welcome to the teaching loads database. Academic staff can use this facility to update their teaching load data throughout the year.</p>";
		echo "\n" . '<ul class="boxylist">';
		if (isSet ($this->users[$this->user])) {echo "\n\t" . '<li><a href="' . $this->baseUrl . "/people/{$this->user}/\"><strong>Submit my details</strong></a></li>";}
		if ($this->userIsAdministrator) {echo "\n\t" . '<li><a href="' . $this->baseUrl . '/terms/"><strong>Show figures for each term</strong></a></li>';}
		echo "\n" . '</ul>';
	}
	
	
	# Function to get the list of terms
	private function getTerms ()
	{
		# Construct a query, assembling the term name dynamically
		$query = "SELECT
			*,
			IF(((CAST(NOW() as DATE) >= opening) AND (CAST(NOW() as DATE) <= closing)), 1, 0) AS editable,
			CONCAT(term,' ',RIGHT(year,2),'/',LPAD((RIGHT(year,2) + 1),2,0)) AS name	/* e.g. 2007 becomes 07/08 */
			FROM {$this->settings['database']}.{$this->settings['termsTable']}
			ORDER BY year,term;";
		
		# Get the data
		$terms = $this->databaseConnection->getData ($query, "{$this->settings['database']}.{$this->settings['termsTable']}");
		
		# Return the data
		return $terms;
	}
	
	
	# Function to get the user details or force them to register
	public function people ($user = false)
	{
		# Select the user
		if (!$this->selectUser ($user)) {
			return false;
		}
		
		# Show the term selection list, with links to the user-specific page
		echo $this->selectTerm ($user);
	}
	
	
	# Function to select a user
	private function selectUser ($user = false, $baseUrl = '/people/')
	{
		# If there is no user, require selection
		if (!$user) {
			echo $this->personSelectionList ();
			return false;
		}
		
		# If the selected user is not in the list, require selection
		if (!array_key_exists ($user, $this->users)) {
			$url = $this->baseUrl . $baseUrl;
			application::sendHeader (301, $_SERVER['_SITE_URL'] . $url);
			echo "\n<p><a href=\"{$url}\">Please click here to select a valid user.</a></p>";
			return false;
		}
		
		# Ensure the user has rights to view the page
		if (!$accessGranted = ($this->userIsAdministrator || ($user == $this->user))) {
			echo "\n<p>You do not appear to have rights to view/edit this page. If you think you should, please <a href=\"{$this->baseUrl}/feedback.html\">contact the administrator</a>.</p>";
			return false;
		}
		
		# Signal success
		return true;
	}
	
	
	# Function to show the results for each term
	public function terms ($term = false)
	{
		# If there is no term, require selection
		if (!$term) {
			echo $this->selectTerm ();
			return false;
		}
		
		# If the selected term is not in the list, require selection
		if (!array_key_exists ($term, $this->terms)) {
			$url = "{$this->baseUrl}/{$this->actions['terms']['url']}/";
			application::sendHeader (301, $_SERVER['_SITE_URL'] . $url);
			echo "\n<p><a href=\"{$url}\">Please click here to select a valid term.</a></p>";
			return false;
		}
		
		# If a user has been supplied, validate it and show the user update screen
		$user = (isSet ($_GET['user']) ? $_GET['user'] : '');
		if ($user) {
			
			# Ensure the user is valid
			if (!$this->selectUser ($user, "/{$this->actions['terms']['url']}{$term}/")) {
				return false;
			}
			
			# Show the user update screen
			$this->userData ($user, $term);
			return;
		}
		
		# If there is no user, show the compiled list
		if (!$user) {
			echo $this->showResults ($term);
			return;
		}
	}
	
	
	# Function to format a user's name
	private function formatName ($user, $addLinkBase = false)
	{
		# Assemble the name
		$person = $this->users[$user];
		$nameHtml = ($person['title'] ? $person['title'] . ' ' : '') . $person['forename'] . ' ' . $person['surname'];
		$nameHtml = htmlspecialchars ($nameHtml);
		
		# Add a link if required
		if ($addLinkBase) {
			$nameHtml = "<a href=\"{$addLinkBase}{$user}/\">{$nameHtml}</a>";
		}
		
		# Return the HTML
		return $nameHtml;
	}
	
	
	# Function to update/show the user's data
	private function userData ($user, $term)
	{
		# Get the data for this term
		$data = $this->getData ($term, $user);
		
		# Create formatted versions of the person's name, the term and the closing editing date
		$name = $this->formatName ($user);
		$termName = $this->terms[$term]['name'];
		$openingDate = timedate::convertBackwardsDateToText ($this->terms[$term]['opening']);
		$closingDate = timedate::convertBackwardsDateToText ($this->terms[$term]['closing']);
		
		# Determine editability
		if (!$this->terms[$term]['editable'] && !$this->userIsAdministrator) {
			echo "\n<p>This data cannot be edited at present.<br />(Editing dates: {$openingDate} - {$closingDate}.)</p>";
			if ($data) {
				unset ($data['id']);
				unset ($data['timestamp']);
				$data['username__JOIN__people__people__reserved'] = $name;
				$data["term__JOIN__{$this->settings['database']}__{$this->settings['termsTable']}__reserved"] = $termName;
				$headings = $this->databaseConnection->getHeadings ($this->settings['database'], $this->settings['table']);
				echo application::htmlTableKeyed ($data, $headings);
			}
			return false;
		}
		
		# Determine fixed data and merge this into the submitted data
		$keys['username__JOIN__people__people__reserved'] = $user;
		$keys["term__JOIN__{$this->settings['database']}__{$this->settings['termsTable']}__reserved"] = $term;
		if ($data) {$keys['id'] = $data['id'];}
		
		# Databind a form
		$form = new form (array (
			'databaseConnection' => $this->databaseConnection,
			'displayRestrictions' => false,
			'formCompleteText' => 'Thank you - the data has now been stored.' . ($this->userIsAdministrator ? " [<a href=\"{$this->baseUrl}/terms/{$term}/\">View all data for this term.</a>]" : ''),
		));
		$form->heading (3, htmlspecialchars ("{$termName} > {$name}"));
		$form->heading ('p', "Please enter below the number of hours for each area of activity, then click 'submit' at the bottom.<br />You can also return to make changes to data here until {$closingDate}.");
		$form->dataBinding (array (
			'database' => $this->settings['database'],
			'table' => $this->settings['table'],
			'intelligence' => true,
			'exclude' => array_keys ($keys),
			'data' => $data,
			'attributes' => array (
				'forums' => array ('description' => 'e.g. graduate presentations event', ),
				'symposiums' => array ('description' => 'e.g. research group symposium', ),
				'workshops' => array ('description' => 'e.g. research group workshop day', ),
				'other' => array ('description' => 'Anything not fitting into the above categories, e.g. seminar, reading group, generic research, graduate research training', ),
			),
		));
		$form->setOutputScreen ();
		if (!$result = $form->process ()) {return false;}
		
		# Merge in the keys
		$result += $keys;
		
		# Take action on the database
		$action = ($data ? 'update' : 'insert');
		$conditions = ($data ? $keys : false);
		if (!$databaseChange = $this->databaseConnection->$action ($this->settings['database'], $this->settings['table'], $result, $conditions)) {
			#!# Report error to admin
		}
	}
	
	
	# Function to retrieve data
	private function getData ($term, $user = false)
	{
		# Define the conditions
		$conditions["term__JOIN__{$this->settings['database']}__{$this->settings['termsTable']}__reserved"] = $term;
		if ($user) {$conditions['username__JOIN__people__people__reserved'] = $user;}
		
		# Get the data
		$action = ($user ? 'selectOne' : 'select');
		$data = $this->databaseConnection->$action ($this->settings['database'], $this->settings['table'], $conditions);
		
		# Return the data
		return $data;
	}
	
	
	# Function to show the results for a term
	private function showResults ($term)
	{
		# Ensure the user is an administrator
		if (!$this->userIsAdministrator) {
			if (isSet ($this->users[$this->user])) {
				echo "\n<p>You can <a href=\"{$this->baseUrl}/terms/{$term}/{$this->user}/\">view/edit your data for this term ({$this->terms[$term]['name']})</a>.</p>";
				echo "\n<br />";
			}
			echo "\n<p>You do not appear to have rights to view/edit the aggregated data. If you think you should, please <a href=\"{$this->baseUrl}/feedback.html\">contact the administrator</a>.</p>";
			return false;
		}
		
		# Determine whether it's an editable term
		$isCurrentTerm = $this->terms[$term]['editable'];
		
		# Get the results for this term
		$dataRaw = $this->getData ($term);
		
		# Convert to using usernames
		$data = array ();
		foreach ($dataRaw as $key => $value) {
			$username = $value['username__JOIN__people__people__reserved'];
			$data[$username] = $value;
		}
		
		# For a current (editable) term, add in empty values for all fields of all the current users if they have not yet submitted
		if ($isCurrentTerm) {
			$keys = $this->databaseConnection->getFieldNames ($this->settings['database'], $this->settings['table']);
			foreach ($this->users as $username => $person) {
				if (!isSet ($data[$username])) {
					$data[$username] = array_fill_keys ($keys, '');
				}
			}
		}
		
		# End if there is no data
		if (!$data) {
			echo "\n<p>No data or users have been found for {$this->terms[$term]['name']}.</p>";
			return false;
		}
		
		# Modify the results to remove irrelevant fields
		foreach ($data as $username => $result) {
			unset ($data[$username]['id']);
			unset ($data[$username]['timestamp']);
			unset ($data[$username]["term__JOIN__{$this->settings['database']}__{$this->settings['termsTable']}__reserved"]);
		}
		
		# Change the name to a readable name
		foreach ($data as $username => $person) {
			$data[$username]['username__JOIN__people__people__reserved'] = $this->formatName ($username, ($isCurrentTerm ? "{$this->baseUrl}/terms/{$term}/" : false));
		}
		
		# Set "surname, forename" for the key (for later sorting)
		$people = array ();
		foreach ($data as $username => $person) {
			if (!isSet ($this->users[$username])) {
				#!# Mail admin
				continue;
			}
			$key = "{$this->users[$username]['surname']}, {$this->users[$username]['forename']}";
			$people[$key] = $person;
		}
		
		# Sort the data
		ksort ($people);
		
		# Add to the end a sum of hours for each user
		foreach ($people as $person => $hours) {
			unset ($hours['username__JOIN__people__people__reserved']);
			unset ($hours['sabbatical']);
			$people[$person]['Total'] = array_sum ($hours);
		}
		
		# Fade out the 'Not on sabbatical' note
		foreach ($people as $person => $hours) {
			if ($people[$person]['sabbatical'] == 'Not on sabbatical') {
				$people[$person]['sabbatical'] = "<span class=\"faded\">{$people[$person]['sabbatical']}</span>";
			}
			if ($people[$person]['fellowship'] == 'Not on a Research Fellowship') {
				$people[$person]['fellowship'] = "<span class=\"faded\">{$people[$person]['fellowship']}</span>";
			}
		}
		
		# Convert 0 to being faded text, and add a sum of hours for each column
		foreach ($people as $person => $hours) {
			foreach ($hours as $key => $value) {
				$people['Totals'][$key] = $value + (isSet ($people['Totals'][$key]) ? $people['Totals'][$key] : 0);
				if ($people[$person][$key] == '0') {$people[$person][$key] = '<span class="faded">0</span>';}
				$people[$person][$key] = preg_replace ('/\.0$/', '', $people[$person][$key]);
			}
		}
		$people['Totals']['username__JOIN__people__people__reserved'] = 'Totals';
		
		# Compile a table
		$headings = $this->databaseConnection->getHeadings ($this->settings['database'], $this->settings['table']);
		$headings['username__JOIN__people__people__reserved'] = 'Name';
		$htmlTable = application::htmlTable ($people, $headings, 'teachingloads border', $keyAsFirstColumn = false, false, $allowHtml = true, $showColons = false, $addCellClasses = false, $addRowKeyClasses = true, $onlyFields = array (), $compress = false, $showHeadings = true);
		
		# Compile the HTML
		$html  = "\n<p>The following data " . ($isCurrentTerm ? 'has so far been submitted' : 'was submitted') . " for {$this->terms[$term]['name']}:</p>";
		if ($isCurrentTerm) {$html .= "\n<p><em>Note: as this term is currently editable, all eligible staff are listed, rather than only those that have submitted.</em></p>";}
		$html .= $htmlTable;
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to show a selection list of staff
	private function personSelectionList ()
	{
		# Compile a list, showing only the current user if they are not an administrator
		$list = array ();
		foreach ($this->users as $username => $person) {
			if (!$this->userIsAdministrator && ($this->user != $username)) {continue;}
			$list[$username] = "<a href=\"{$this->baseUrl}/people/{$username}/\">" . htmlspecialchars ("{$person['title']} {$person['forename']} {$person['surname']}") . '</a>';
		}
		$html  = "\n<p>You can view/update the details for:</p>";
		$html .= application::htmlUl ($list);
		
		# Show the list and end
		return $html;
	}
	
	
	# Function to show a selection list of terms
	private function selectTerm ($user = false)
	{
		# Compile a list
		$list = array ();
		if ($user) {$username = $this->formatName ($user);}
		foreach ($this->terms as $key => $term) {
			$link = "{$this->baseUrl}/terms/{$key}/" . ($user ? "{$user}/" : '');
			$text = htmlspecialchars ($term['name'] . ($user ? ' > ' . $username : ''));
			if ($term['editable']) {$text = "<strong>{$text}</strong>";}
			$list[$key] = "<a href=\"{$link}\">{$text}</a>" . ($term['editable'] ? ' &nbsp;<span class="comment">(currently editable' . ($user ? '' : ' by staff') . ')</span>' : '');
		}
		$html  = "\n<p>Please select which term:</p>";
		$html .= application::htmlUl ($list);
		
		# Show the list and end
		return $html;
	}
}

?>
