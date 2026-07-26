"""
Video Validator and Optimizer
Validates video specs and optimizes for YouTube Shorts
"""
import os
from pathlib import Path
from typing import Dict, Optional, Tuple
import sys

PYTHON_DIR = str(Path(__file__).resolve().parent.parent)
if PYTHON_DIR not in sys.path:
    sys.path.insert(0, PYTHON_DIR)

from media_probe import find_ffmpeg, find_ffprobe, probe_media


class VideoValidator:
    """Validate and optimize videos for YouTube Shorts"""
    
    # YouTube Shorts specifications
    MAX_DURATION = 60  # seconds
    OPTIMAL_WIDTH = 1080
    OPTIMAL_HEIGHT = 1920
    OPTIMAL_ASPECT_RATIO = 9/16
    
    ALLOWED_FORMATS = ['.mp4', '.mov', '.avi', '.mkv']
    
    def __init__(self):
        """Initialize validator"""
        self.ffmpeg_path = self._find_ffmpeg()
        self.ffprobe_path = self._find_ffprobe()
    
    def _find_ffmpeg(self) -> Optional[str]:
        """Find FFmpeg binary without MoviePy."""
        try:
            return find_ffmpeg()
        except FileNotFoundError:
            return None

    def _find_ffprobe(self) -> Optional[str]:
        """Find FFprobe binary without MoviePy."""
        try:
            return find_ffprobe()
        except FileNotFoundError:
            return None

    def validate(self, video_path: str) -> Tuple[bool, Dict]:
        """
        Validate video for YouTube Shorts
        
        Args:
            video_path: Path to video file
            
        Returns:
            (is_valid, info_dict)
        """
        if not os.path.exists(video_path):
            return False, {'error': 'File not found'}
        
        # Check file extension
        ext = Path(video_path).suffix.lower()
        if ext not in self.ALLOWED_FORMATS:
            return False, {'error': f'Unsupported format: {ext}'}
        
        # Get video info
        info = self.get_video_info(video_path)
        if not info:
            return False, {'error': 'Could not read video info'}
        
        issues = []
        warnings = []
        
        # Check duration
        duration = info.get('duration', 0)
        if duration > self.MAX_DURATION:
            issues.append(f"Duration too long: {duration}s (max {self.MAX_DURATION}s)")
        elif duration < 1:
            issues.append("Duration too short")
        
        # Check dimensions
        width = info.get('width', 0)
        height = info.get('height', 0)
        
        if width < 360 or height < 360:
            issues.append(f"Resolution too low: {width}x{height}")
        
        aspect_ratio = width / height if height > 0 else 0
        expected_ratio = self.OPTIMAL_ASPECT_RATIO
        
        if abs(aspect_ratio - expected_ratio) > 0.1:
            warnings.append(f"Non-standard aspect ratio: {width}:{height} (expected 9:16)")
        
        # Check codec
        video_codec = info.get('codec_name', '')
        if video_codec not in ['h264', 'hevc', 'vp9']:
            warnings.append(f"Non-standard codec: {video_codec}")
        
        # Build result
        result = {
            'info': info,
            'issues': issues,
            'warnings': warnings,
            'recommendations': []
        }
        
        # Add recommendations
        if width != self.OPTIMAL_WIDTH or height != self.OPTIMAL_HEIGHT:
            result['recommendations'].append(
                f"Optimal resolution: {self.OPTIMAL_WIDTH}x{self.OPTIMAL_HEIGHT}"
            )
        
        is_valid = len(issues) == 0
        
        return is_valid, result
    
    def get_video_info(self, video_path: str) -> Optional[Dict]:
        """Get normalized video metadata using ffprobe."""
        if not self.ffprobe_path:
            print("ffprobe not found")
            return None
        try:
            info = probe_media(video_path)
            video = info.get('video')
            if not video:
                return None
            return {
                'width': video['width'],
                'height': video['height'],
                'duration': info['duration'],
                'codec_name': video['codec_name'],
                'bit_rate': info['bit_rate'],
                'size': info['size'],
                'format_name': info['format_name'],
                'frame_rate': video['frame_rate'],
            }
        except Exception as e:
            print(f"ffprobe error: {e}")
            return None

    def print_validation_result(self, is_valid: bool, result: Dict):
        """Print validation result"""
        print("\n📹 Video Validation")
        print("=" * 50)
        
        if 'info' in result:
            info = result['info']
            print(f"\n📊 Video Info:")
            print(f"   Resolution: {info['width']}x{info['height']}")
            print(f"   Duration: {info['duration']:.1f}s")
            print(f"   Codec: {info['codec_name']}")
            print(f"   Size: {info['size'] / (1024*1024):.1f} MB")
            print(f"   Frame Rate: {info['frame_rate']:.1f} fps")
        
        if result.get('issues'):
            print(f"\n❌ Issues ({len(result['issues'])}):")
            for issue in result['issues']:
                print(f"   • {issue}")
        
        if result.get('warnings'):
            print(f"\n⚠️  Warnings ({len(result['warnings'])}):")
            for warning in result['warnings']:
                print(f"   • {warning}")
        
        if result.get('recommendations'):
            print(f"\n💡 Recommendations:")
            for rec in result['recommendations']:
                print(f"   • {rec}")
        
        if is_valid:
            print(f"\n✅ Video is valid for YouTube Shorts")
        else:
            print(f"\n❌ Video is NOT valid for YouTube Shorts")


def main():
    """CLI test"""
    import sys
    
    if len(sys.argv) < 2:
        print("Usage: python video_validator.py <video_path>")
        sys.exit(1)
    
    video_path = sys.argv[1]
    
    validator = VideoValidator()
    is_valid, result = validator.validate(video_path)
    validator.print_validation_result(is_valid, result)
    
    sys.exit(0 if is_valid else 1)


if __name__ == '__main__':
    main()
