#MimeMail PHP Class

PHP Class object for use in generating RFC MIME compliant email message headers and bodies. Supports plain text, HTML or Multipart message formats. Simple clear methods allow you to quickly generate one-off or personalized messages in masse. Works with PHP's mail() function or MTAs like qmail and Sendmail. Outputs (1) string headers formatted to RFC MIME specifications and (2) string body with optional quoted-printable encoding (recommended for HTML messages).

Based on RFC 2821, MIME RFCs and the excellent MIME::Lite PERL module.

Private methods and vars begin with an underscore (_).

See file mimemail.txt for full documentation in VIM style.

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.