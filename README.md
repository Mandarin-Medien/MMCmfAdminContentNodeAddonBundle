# MMCmfAdminContentNodeAddonBundle
Integrates a CRUD/Administration-GUI of MMCmfContentNode into the MMAdminBundle.


### Append to src/MY_CUSTOM_ADMIN_BUNDLE/config/routing.yml

```
...

mm_cmf_content_node_addon:
    resource: "@MMCmfAdminContentNodeAddonBundle/Resources/config/routing.yml"
    prefix:   /contentnode

...
```