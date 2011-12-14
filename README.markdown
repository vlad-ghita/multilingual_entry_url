Field: Multilingual Image upload
==============

The multilingual version of the specialized field for image uploads.

* Version: 1.0beta
* Build Date: 2011-12-14
* Authors:
	- [Xander Group](http://www.xanderadvertising.com)
	- Vlad Ghita
* Requirements:
	- Symphony 2.0 or above
	- [Entry URL Field](https://github.com/vlad-ghita/image_upload) at least version 1.1
	- [Page LHandles](https://github.com/vlad-ghita/page_lhandles) at least version 2.1
	- [Frontend Localisation](https://github.com/vlad-ghita/frontend_localisation) at least version 0.4beta

Thank you all other Symphony & Extensions developers for your inspirational work.



# 1 About #

When adding this field to a section, the following options are available to you:

* **Anchor Label** is the text used for the hyperlink in the backend
* **Anchor URL** is the URL of your entry view page on the frontend. An `<entry id="123">...</entry>` nodeset is provided from which you can grab field values, just as you would from a datasource. For example:

		/members/profile/{entry/name/@handle}/

* **Open links in a new window** enforces the hyperlink to spawn a new tab/window
* **Hide this field on publish page** hides the hyperlink in the entry edit form



# 2 Installation #
 
1. Upload the 'multilingual_entry_url' folder in this archive to your Symphony 'extensions' folder.
2. Enable it by selecting the "Field: Multilingual Entry URL", choose Enable from the with-selected menu, then click Apply.
3. The field will be available in the list when creating a Section.



# 3 Compatibility #

   Symphony | Field: Multilingual Entry URL
------------|----------------
2.0 — *     | [latest](https://github.com/vlad-ghita/multilingual_entry_url)

Entry URL Field | Field: Multilingual Entry URL
----------------|----------------
[1.1.4](https://github.com/vlad-ghita/image_upload) — *     | [latest](https://github.com/vlad-ghita/multilingual_entry_url)

  Page LHandles | Field: Multilingual Entry URL
----------------|----------------
  [2.1](https://github.com/vlad-ghita/page_lhandles) — *     | [latest](https://github.com/vlad-ghita/multilingual_entry_url)
  
Frontend Localisation | Field: Multilingual Entry URL
----------------------|----------------
    [0.4beta](https://github.com/vlad-ghita/frontend_localisation) — *     | [latest](https://github.com/vlad-ghita/multilingual_entry_url)




# 4 Changelog #

- 1.0beta : 14 dec 2011
    * Initial beta release