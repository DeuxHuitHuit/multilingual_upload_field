Field: Multilingual File Upload
==============

A field that allows file upload for different frontend languages.

* Version: 0.1 beta
* Build Date: 2011-11-07
* Authors:
	- [Xander Group](http://www.xandergroup.ro)
	- Vlad Ghita
* Requirements:
	- Symphony 2.2 or above
	- [Frontend Localisation extension](https://github.com/vlad-ghita/frontend_localisation)

Thank you all other Symphony & Extensions developers for your inspirational work.<br />
Cheers, [@Guilleme](https://github.com/6ui11em). Thanks again for UI Tabs. It's already third extension where I'm using them :)

<span style="color:red">Still beta !</span><br />
This means: if something breaks, please bear with me and report the bug. Thank you !

# 1 About #

This is a multilingual version of the classic upload field. It works the same way as a single upload field, but supports multiple languages, the same way as [Multilingual text](https://github.com/6ui11em/multilingual_field). It offers on demand unique file names and outputs to Frontend the filename for current Frontend language code.

**VERY IMPORTANT**<br />
This extension depends on [Frontend Localisation](https://github.com/vlad-ghita/frontend_localisation). From there it draws it's Frontend language information. This way I'm trying to decouple my multilingual stuff from various Language drivers out there.<br />
Get Frontend Localisation, a language driver (Language Redirect for example) and you're good to go.




# 2 Installation #

1. Upload the 'multilingual_upload_field' folder in this archive to your Symphony `extensions` folder.

2. Enable it by selecting the "Field: Multilingual File Upload", choose Enable from the with-selected menu, then click Apply.

3. You can now add the "Multilingual File Upload" field to your sections.




# 3 Compatibility #

         Symphony | Multilingual File Upload
------------------|--------------------------
      2.0 — 2.1.* | Not compatible
      2.2 - *     | [latest](https://github.com/vlad-ghita/multilingual_upload_field)

Frontend Localisation | Multilingual File Upload
----------------------|--------------------------
                    * | [latest](https://github.com/vlad-ghita/multilingual_upload_field)



# 4 Changelog #

* 0.1 beta, 2011-11-07
	* initial release
