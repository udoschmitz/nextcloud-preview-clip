ARG NEXTCLOUD_BASE_TAG=33-apache
FROM nextcloud:${NEXTCLOUD_BASE_TAG}
RUN apt-get update && apt-get install -y zstd && rm -rf /var/lib/apt/lists/*

# Clip Studio preview provider
COPY ClipStudio.php /usr/src/nextcloud/lib/private/Preview/ClipStudio.php
COPY ClipStudio.php /var/www/html/lib/private/Preview/ClipStudio.php

# Register provider in PreviewManager (source + runtime tree)
COPY patch_preview_manager.php /usr/local/bin/patch_preview_manager.php
RUN php /usr/local/bin/patch_preview_manager.php

# Files UI: tolerate has-preview/fileid variants for preview URL generation
COPY patch_files_dist_haspreview.py /usr/local/bin/patch_files_dist_haspreview.py
COPY patch_files_dist_haspreview.sh /usr/local/bin/patch_files_dist_haspreview.sh
RUN chmod +x /usr/local/bin/patch_files_dist_haspreview.py /usr/local/bin/patch_files_dist_haspreview.sh \
	&& /usr/local/bin/patch_files_dist_haspreview.sh \
	&& rm -f /usr/local/bin/patch_files_dist_haspreview.sh

# Runtime hook: reapply patches when /var/www/html is a mounted volume
COPY entrypoint-apply-preview-customizations.sh /usr/local/bin/entrypoint-apply-preview-customizations.sh
RUN chmod +x /usr/local/bin/entrypoint-apply-preview-customizations.sh

ENTRYPOINT ["/usr/local/bin/entrypoint-apply-preview-customizations.sh"]

