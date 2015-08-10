<div class="field_wrap" {include file="web/partials/data_attributes.tpl" data=$form_element.data inline}>
    <div class="ui-field-contain{if $form_element.class} {$form_element.class|escape:'html'}{/if}">
        <label {if $form_element.id}for="{$form_element.id}"{/if}>{$form_element.label|default:'Textový vstup'}</label>
        <input type="file" name="{$form_element.name|default:'unknown_name'}" {if $form_element.id}id="{$form_element.id}"{/if} {if $form_element.placeholder} placeholder="{$form_element.placeholder|escape:'html'}"{/if}
               {if form_error($form_element.name) neq ''}data-theme="b"{/if}
               {if $form_element.disabled eq TRUE}disabled="disabled"{/if} />
    </div>
    <div{if $form_element.class} class="{$form_element.class|escape:'html'}"{/if}>
    {if $form_element.hint}<p><em>{$form_element.hint}</em></p>{/if}
    {if $form_element.name}{form_error($form_element.name, '<div class="ui-bar ui-bar-b ui-corner-all">', '</div>')}{/if}
    </div>
</div>