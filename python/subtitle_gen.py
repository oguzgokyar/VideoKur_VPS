def split_text_to_chunks(text: str, max_words: int = 5) -> list:
    """Metni maksimum kelime sayısına göre parçalara böler."""
    words = text.split()
    if not words:
        return ['']
    chunks = []
    for i in range(0, len(words), max_words):
        chunk = ' '.join(words[i:i + max_words])
        chunks.append(chunk)
    return chunks if chunks else ['']


def generate_srt(scenes: list, output_path: str, max_words_per_line: int = 5) -> str:
    """Script sahnelerinden SRT altyazı dosyası üretir.
    Her satırda en fazla max_words_per_line kelime gösterilir.
    Seslendirme ile senkronize ilerler - toplam süre korunur."""
    srt_lines = []
    current_time = 0
    entry_index = 1

    for scene in scenes:
        duration = scene.get('duration', 6)
        text = scene.get('text', '')
        
        # Metni parçalara böl
        chunks = split_text_to_chunks(text, max_words_per_line)
        num_chunks = len(chunks)
        
        # Süreyi parçalara eşit dağıt (toplam süre korunur)
        if num_chunks > 0:
            chunk_duration = duration / num_chunks
        else:
            chunk_duration = duration
        
        for chunk in chunks:
            start = format_time(current_time)
            end = format_time(current_time + chunk_duration)
            
            srt_lines.append(f"{entry_index}")
            srt_lines.append(f"{start} --> {end}")
            srt_lines.append(chunk)
            srt_lines.append("")  # Boş satır
            
            entry_index += 1
            current_time += chunk_duration

    srt_content = '\n'.join(srt_lines)
    
    with open(output_path, 'w', encoding='utf-8') as f:
        f.write(srt_content)

    return srt_content


def format_time(seconds: float) -> str:
    """Saniyeyi SRT zaman formatına çevirir."""
    hours = int(seconds // 3600)
    minutes = int((seconds % 3600) // 60)
    secs = int(seconds % 60)
    millis = int((seconds - int(seconds)) * 1000)
    return f"{hours:02d}:{minutes:02d}:{secs:02d},{millis:03d}"


if __name__ == '__main__':
    test_scenes = [
        {"scene": 1, "text": "Son dakika haberi!", "duration": 4},
        {"scene": 2, "text": "Detaylar az sonra.", "duration": 5},
        {"scene": 3, "text": "Takipte kalın!", "duration": 3}
    ]
    result = generate_srt(test_scenes, 'test_subtitles.srt')
    print(result)
