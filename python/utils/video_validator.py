"""
Video Validator and Optimizer
Validates video specs and optimizes for YouTube Shorts
"""
import os
import subprocess
import json
from pathlib import Path
from typing import Dict, Optional, Tuple


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
        """Find FFmpeg binary"""
        try:
            from moviepy.config import FFMPEG_BINARY
            return FFMPEG_BINARY
        except Exception as e:
            print(f"[DEBUG] MoviePy FFmpeg config unavailable: {e}")
            # Try system PATH
            import shutil
            return shutil.which('ffmpeg')
    
    def _find_ffprobe(self) -> Optional[str]:
        """Find FFprobe binary"""
        import shutil
        return shutil.which('ffprobe')
    
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
        """
        Get video metadata using ffprobe
        
        Args:
            video_path: Path to video file
            
        Returns:
            Dict with video info or None
        """
        if not self.ffprobe_path:
            print("⚠️  ffprobe not found, using basic info")
            return self._get_basic_info(video_path)
        
        try:
            cmd = [
                self.ffprobe_path,
                '-v', 'quiet',
                '-print_format', 'json',
                '-show_format',
                '-show_streams',
                video_path
            ]
            
            result = subprocess.run(cmd, capture_output=True, text=True)
            
            if result.returncode != 0:
                return None
            
            data = json.loads(result.stdout)
            
            # Find video stream
            video_stream = None
            for stream in data.get('streams', []):
                if stream.get('codec_type') == 'video':
                    video_stream = stream
                    break
            
            if not video_stream:
                return None
            
            format_info = data.get('format', {})
            
            return {
                'width': int(video_stream.get('width', 0)),
                'height': int(video_stream.get('height', 0)),
                'duration': float(format_info.get('duration', 0)),
                'codec_name': video_stream.get('codec_name', ''),
                'bit_rate': int(format_info.get('bit_rate', 0)),
                'size': int(format_info.get('size', 0)),
                'format_name': format_info.get('format_name', ''),
                'frame_rate': self._parse_frame_rate(video_stream.get('r_frame_rate', ''))
            }
            
        except Exception as e:
            print(f"ffprobe error: {e}")
            return None
    
    def _get_basic_info(self, video_path: str) -> Optional[Dict]:
        """Get basic video info using moviepy"""
        try:
            from moviepy import VideoFileClip
            clip = VideoFileClip(video_path)
            
            info = {
                'width': clip.w,
                'height': clip.h,
                'duration': clip.duration,
                'codec_name': 'unknown',
                'bit_rate': 0,
                'size': os.path.getsize(video_path),
                'format_name': 'mp4',
                'frame_rate': clip.fps
            }
            
            clip.close()
            return info
            
        except Exception as e:
            print(f"moviepy error: {e}")
            return None
    
    def _parse_frame_rate(self, frame_rate_str: str) -> float:
        """Parse frame rate string like '30/1' to float"""
        try:
            if '/' in frame_rate_str:
                num, den = frame_rate_str.split('/')
                return float(num) / float(den)
            return float(frame_rate_str)
        except Exception as e:
            print(f"[WARN] Invalid frame rate format '{frame_rate_str}': {e}")
            return 0.0
    
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
