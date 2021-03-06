import { DOCUMENT } from '@angular/platform-browser';
import { Component, Inject, OnInit, OnDestroy, AfterViewInit, ChangeDetectorRef } from '@angular/core';
import { MatBottomSheetRef, MAT_BOTTOM_SHEET_DATA } from '@angular/material';

import { AlertService } from '../../../../app/core/services/alert/alert.service';
import { AudioDataService } from '../../../../app/core/services/user/audioData.service';
import { PlayerService } from '../../../../app/core/services/player/player.service';
import { SessionService } from '../../../../app/core/services/session/session.service';

import { environment } from '../../../../environments/environment';

declare var global: any;

@Component({
	selector: 'app-showMobilePlayer',
	templateUrl: './showMobilePlayer.component.html'
})
export class ShowMobilePlayerComponent implements OnInit, OnDestroy, AfterViewInit {
	public environment: any = environment;
	public window: any = global;
	public sessionData: any = [];
	public translations: any = [];
	public audioPlayerData: any = [];
	public noData: boolean;
	public audio: any;
	public showPlayer: boolean;
	public showPlayerAnimation: boolean;
	public rippleColor: string = 'rgba(255, 255, 255, .15)';
	public activeDetectChanges: boolean = true;

	constructor(
		@Inject(DOCUMENT) private document: Document,
		@Inject(MAT_BOTTOM_SHEET_DATA) public data: any,
		private bottomSheetRef: MatBottomSheetRef<ShowMobilePlayerComponent>,
		private alertService: AlertService,
		private audioDataService: AudioDataService,
		private playerService: PlayerService,
		private sessionService: SessionService,
		private ref: ChangeDetectorRef
	) {
		this.sessionData = this.data.sessionData;
		this.audioPlayerData = this.data.playerData;
		this.translations = this.data.translations;
		this.audio = this.data.audio;
	}

	ngOnInit() {
		// not in use
	}

	ngOnDestroy() {
		this.activeDetectChanges = false;
	}

	ngAfterViewInit() {
		let self = this;
		
		// Auto progress bar
		this.audio.addEventListener('timeupdate', function() {
			let countDown = Math.round(self.audio.duration - self.audio.currentTime);
			self.audioPlayerData.current.time = self.formatTime(self.audio.currentTime);

			// Detect changes when song is playing
			self.detectChanges();

			// Countdown time
			let durationCountDown = self.formatTime(countDown);
			self.audioPlayerData.current.duration = parseInt(durationCountDown.split(':')[1]) == 0 ? self.audioPlayerData.list[self.audioPlayerData.current.key].duration : durationCountDown;
			self.audioPlayerData.list[self.audioPlayerData.current.key].countdown = parseInt(durationCountDown.split(':')[1]) == 0 ? self.audioPlayerData.list[self.audioPlayerData.current.key].duration : durationCountDown;

			// Progress bar
			let progress = ((self.audio.currentTime / self.audio.duration) * 1000);
			self.audioPlayerData.current.progress = progress;
		});
	}

	// Refresh data on sheet / progresbar ant duration
	detectChanges() {
		if (this.activeDetectChanges)
			this.ref.detectChanges();
	}

	// repeat · next · play/pause · prev · shuffle
	playTrack(type, key) {
		switch(type){
			case('item'):
				this.audioPlayerData.key = key;
				this.audioPlayerData.buttonType = 'item';
				this.playerService.setPlayTrack(this.audioPlayerData);
			break;
			case('play'):
				this.playTrack('item', this.audioPlayerData.key);
			break;
			case('prev'):
				this.audioPlayerData.key = key;
				this.audioPlayerData.buttonType = 'prev';
				this.playerService.setPlayTrack(this.audioPlayerData);
			break;
			case('next'):
				this.audioPlayerData.key = key;
				this.audioPlayerData.buttonType = 'next';
				this.playerService.setPlayTrack(this.audioPlayerData);
			break;
			case('shuffle'):
				this.audioPlayerData.shuffle = !this.audioPlayerData.shuffle;
				this.playerService.setCurrentTrack(this.audioPlayerData);
			break;
			case('repeat'):
				this.audioPlayerData.repeat = !this.audioPlayerData.repeat;
				this.playerService.setCurrentTrack(this.audioPlayerData);
			break;
		}
	}

	// Time format
	formatTime(time) {
		let duration = time,
			hours = Math.floor(duration / 3600),
			minutes = Math.floor((duration % 3600) / 60),
			seconds = Math.floor(duration % 60),
			result = [];

		if (hours)
			result.push(hours)

		result.push(((hours ? "0" : "") + (minutes ? minutes : 0)).substr(-2));
		result.push(("0" + (seconds ? seconds : 0)).substr(-2));

		return result.join(":");
	}

	// Item options: add/remove, share, search, report
	itemAudiosOptions(type, item, playlist) {
		switch(type){
			case("addRemoveSession"):
				item.addRemoveSession = !item.addRemoveSession;
				item.type = item.addRemoveSession ? 'remove' : 'add';

				let dataARS = {
					user: this.sessionData.current.id,
					type: item.type,
					location: 'session',
					id: item.id
				}

				this.audioDataService.addRemove(dataARS)
					.subscribe(res => {
						let song = item.original_title ? (item.original_artist + ' - ' + item.original_title) : item.title,
							text = !item.addRemoveSession ? ' has been added successfully' : ' has been removed';
						
						this.alertService.success(song + text);
					}, error => {
						this.alertService.success('An error has ocurred');
					});
			break;
			case("addRemoveUser"):
				item.addRemoveUser = !item.addRemoveUser;
				item.type = item.addRemoveUser ? 'add' : 'remove';

				let dataARO = {
					user: this.sessionData.current.id,
					type: item.type,
					location: 'user',
					id: item.insertedId,
					item: item.song
				}

				this.audioDataService.addRemove(dataARO)
					.subscribe(res => {
						item.insertedId = res.json();
						
						let song = item.original_title ? (item.original_artist + ' - ' + item.original_title) : item.title,
							text = item.addRemoveUser ? ' has been added successfully' : ' has been removed';
						
						this.alertService.success(song + text);
					}, error => {
						this.alertService.success('An error has ocurred');
					});
			break;
			case("playlist"):
				item.type = !item.addRemoveUser ? "add" : "remove";

				let dataP = {
					user: this.sessionData.current.id,
					type: item.type,
					location: 'playlist',
					item: item.song,
					playlist: playlist.idPlaylist
				}

				this.audioDataService.addRemove(dataP)
					.subscribe(res => {
						let song = item.original_title ? (item.original_artist + ' - ' + item.original_title) : item.title;
						this.alertService.success(song + ' has been added to ' + playlist.title);
					}, error => {
						this.alertService.success('An error has ocurred');
					});
			break;
			case("createPlaylist"):
				let data = 'create';
				this.sessionService.setDataCreatePlaylist(data);
			break;
			case("report"):
				item.type = 'audio';
				this.sessionService.setDataReport(item);
			break;
		}
	}

	// Progress bar
	progressBar(event) {
		let time = ((event.value / 1000) * this.audio.duration);
		this.audio.currentTime = time;
	}

	// Close sheet
	close() {
		this.bottomSheetRef.dismiss();
	}
}