<div class="ffb-preview">
    <div class="ffb-preview-text">{{ str(strip_tags($field->text ?? ''))->squish()->limit(200) }}</div>
</div>
