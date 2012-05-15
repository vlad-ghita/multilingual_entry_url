Multilingual Entry URL
======================

The multilingual version of the entry url field.


## 1 About ##

When adding this field to a section, the following options are available to you:

* **Anchor Label** is the text used for the hyperlink in the backend
* **Anchor URL** is the URL of your entry view page on the frontend. An `<entry id="123">...</entry>` nodeset is provided from which you can grab field values, just as you would from a datasource. For example:

		/members/profile/{entry/name/@handle}/

* **For `Multilingual Text`**, you can use `$language_code` (without `{}`) as a placeholder for language codes. It will be replaced for each language on URL generation.<br />
Let's say my `Articles` Section with a `Multilingual text` field called `Title`. Given an `Articles` Page with an URL param pointing to `Title`, an expression like this must be used:

		/articles/{//title/@*[name() = concat('handle-', $language_code)]}/   --> make sure you know the XML output of Multilingual Text

* **Open links in a new window** enforces the hyperlink to spawn a new tab/window
* **Hide this field on publish page** hides the hyperlink in the entry edit form



### Note about compatibility ###

Currently the URL is generated this way:

    /__LANGUAGE-CODE__/URL



## 2 Installation ##
 
1. Upload the 'multilingual_entry_url' folder in this archive to your Symphony 'extensions' folder.
2. Enable it by selecting the "Field: Multilingual Entry URL", choose Enable from the with-selected menu, then click Apply.
3. The field will be available in the list when creating a Section.
