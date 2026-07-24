"""
Job Resume Helper
Detects which pipeline stage to resume from based on existing output files
"""
import os
import json
from pathlib import Path


class ResumePoint:
    """Pipeline resume points in order"""
    SCRAPING = 'scraping'
    SCRIPTING = 'scripting'
    IMAGING = 'imaging'
    TTS = 'tts'
    SUBTITLING = 'subtitling'
    COMPOSING = 'composing'
    DONE = 'done'
    
    # Ordered list for progression
    STAGES = [SCRAPING, SCRIPTING, IMAGING, TTS, SUBTITLING, COMPOSING, DONE]


def detect_resume_point(job_id: str, base_dir: str = None) -> dict:
    """
    Detect which pipeline stage to resume from based on existing files.
    
    Args:
        job_id: Job ID to check
        base_dir: Base directory (default: auto-detect from script location)
        
    Returns:
        dict with:
            - resume_from: Stage to resume from
            - completed_stages: List of completed stages
            - missing_files: List of missing files for next stage
            - can_resume: Boolean - whether resume is possible
            - message: Human-readable status message
    """
    if base_dir is None:
        base_dir = Path(__file__).parent.parent.parent
    
    output_dir = Path(base_dir) / 'output' / job_id
    jobs_dir = Path(base_dir) / 'data' / 'jobs'
    job_file = jobs_dir / f"{job_id}.json"
    
    # Check if job exists
    if not job_file.exists():
        return {
            'resume_from': None,
            'completed_stages': [],
            'missing_files': [],
            'can_resume': False,
            'message': f'Job file not found: {job_id}'
        }
    
    # Check if output directory exists
    if not output_dir.exists():
        return {
            'resume_from': ResumePoint.SCRAPING,
            'completed_stages': [],
            'missing_files': ['output directory'],
            'can_resume': True,
            'message': 'No output files - will start from beginning'
        }
    
    completed = []
    resume_from = ResumePoint.SCRAPING
    missing_files = []
    
    # Check scraping stage
    news_file = output_dir / 'news.json'
    if news_file.exists():
        completed.append(ResumePoint.SCRAPING)
        resume_from = ResumePoint.SCRIPTING
    else:
        missing_files.append('news.json')
        return _build_result(ResumePoint.SCRAPING, completed, missing_files, True,
                            'Will start from scraping stage')
    
    # Check scripting stage
    script_file = output_dir / 'script.json'
    if script_file.exists():
        completed.append(ResumePoint.SCRIPTING)
        resume_from = ResumePoint.IMAGING
    else:
        missing_files.append('script.json')
        return _build_result(ResumePoint.SCRIPTING, completed, missing_files, True,
                            'Will resume from script generation')
    
    # Check imaging stage
    images_dir = output_dir / 'images'
    if images_dir.exists():
        # Check for key images
        hook_img = images_dir / 'hook.png'
        scene_1 = images_dir / 'scene_1.png'
        
        if hook_img.exists() or scene_1.exists():
            completed.append(ResumePoint.IMAGING)
            resume_from = ResumePoint.TTS
        else:
            missing_files.append('images/*.png')
            return _build_result(ResumePoint.IMAGING, completed, missing_files, True,
                                'Will resume from image generation')
    else:
        missing_files.append('images/')
        return _build_result(ResumePoint.IMAGING, completed, missing_files, True,
                            'Will resume from image generation')
    
    # Check TTS stage
    audio_file = output_dir / 'audio.mp3'
    audio_segments_dir = output_dir / 'audio_segments'
    
    if audio_file.exists() or (audio_segments_dir.exists() and 
                                any(audio_segments_dir.glob('*.mp3'))):
        completed.append(ResumePoint.TTS)
        resume_from = ResumePoint.SUBTITLING
    else:
        missing_files.append('audio.mp3')
        return _build_result(ResumePoint.TTS, completed, missing_files, True,
                            'Will resume from TTS generation')
    
    # Check subtitling stage
    srt_file = output_dir / 'subtitles.srt'
    if srt_file.exists():
        completed.append(ResumePoint.SUBTITLING)
        resume_from = ResumePoint.COMPOSING
    else:
        missing_files.append('subtitles.srt')
        return _build_result(ResumePoint.SUBTITLING, completed, missing_files, True,
                            'Will resume from subtitle generation')
    
    # Check composing stage
    video_file = output_dir / 'final_video.mp4'
    if video_file.exists():
        completed.append(ResumePoint.COMPOSING)
        resume_from = ResumePoint.DONE
        return _build_result(ResumePoint.DONE, completed, [], False,
                            'Job already completed - video exists')
    else:
        missing_files.append('final_video.mp4')
        return _build_result(ResumePoint.COMPOSING, completed, missing_files, True,
                            'Will resume from video composition (fastest!)')


def _build_result(resume_from: str, completed: list, missing: list, 
                 can_resume: bool, message: str) -> dict:
    """Build standardized result dict"""
    return {
        'resume_from': resume_from,
        'completed_stages': completed,
        'missing_files': missing,
        'can_resume': can_resume,
        'message': message,
        'progress': f"{len(completed)}/{len(ResumePoint.STAGES)-1}",  # Exclude DONE
        'progress_percent': int((len(completed) / (len(ResumePoint.STAGES)-1)) * 100)
    }


def get_resume_command(job_id: str, resume_info: dict) -> str:
    """
    Generate command line to resume a job.
    
    Args:
        job_id: Job ID
        resume_info: Result from detect_resume_point()
        
    Returns:
        Command string to execute
    """
    resume_from = resume_info.get('resume_from')
    
    if not resume_info.get('can_resume'):
        return None
    
    # For now, regenerate from the failed stage
    # Later we can optimize to use a dedicated resume script
    return f"python python/regenerate.py {job_id} {resume_from}"


def get_stage_description(stage: str) -> str:
    """Get human-readable stage description"""
    descriptions = {
        ResumePoint.SCRAPING: 'Scraping news article',
        ResumePoint.SCRIPTING: 'Generating video script',
        ResumePoint.IMAGING: 'Generating images',
        ResumePoint.TTS: 'Generating voiceover',
        ResumePoint.SUBTITLING: 'Generating subtitles',
        ResumePoint.COMPOSING: 'Composing final video',
        ResumePoint.DONE: 'Job completed'
    }
    return descriptions.get(stage, stage)


if __name__ == '__main__':
    # CLI interface for testing
    import sys
    import argparse
    
    parser = argparse.ArgumentParser(description='Job Resume Helper')
    parser.add_argument('job_id', help='Job ID to analyze')
    parser.add_argument('--json', action='store_true', help='Output as JSON')
    args = parser.parse_args()
    
    result = detect_resume_point(args.job_id)
    
    if args.json:
        import json
        print(json.dumps(result, indent=2))
    else:
        print("=" * 60)
        print(f"Resume Analysis: {args.job_id}")
        print("=" * 60)
        print(f"Can Resume: {result['can_resume']}")
        print(f"Resume From: {result['resume_from']}")
        print(f"Progress: {result['progress']} ({result['progress_percent']}%)")
        print(f"\nCompleted Stages:")
        for stage in result['completed_stages']:
            print(f"  ✓ {get_stage_description(stage)}")
        print(f"\nNext Stage: {get_stage_description(result['resume_from'])}")
        print(f"\nMessage: {result['message']}")
        
        if result['missing_files']:
            print(f"\nMissing Files:")
            for f in result['missing_files']:
                print(f"  - {f}")
        
        cmd = get_resume_command(args.job_id, result)
        if cmd:
            print(f"\nResume Command:")
            print(f"  {cmd}")
